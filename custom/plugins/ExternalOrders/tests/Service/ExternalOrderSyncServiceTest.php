<?php declare(strict_types=1);

namespace ExternalOrders\Tests\Service;

use ExternalOrders\Entity\ExternalOrderCollection;
use ExternalOrders\Entity\ExternalOrderDefinition;
use ExternalOrders\Entity\ExternalOrderEntity;
use ExternalOrders\Service\ExternalOrderSyncService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ExternalOrderSyncServiceTest extends TestCase
{
    public function testSyncNewOrdersLogsWarningWhenOrdersPayloadIsInvalid(): void
    {
        $context = Context::createDefaultContext();

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('upsert');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->with(false)->willReturn(['orders' => 'invalid']);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $configService = $this->createConfigService('https://example.test/api/orders');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('External Orders sync skipped: API response does not contain orders.');

        $service = new ExternalOrderSyncService($repository, $httpClient, $configService, $logger);

        $service->syncNewOrders($context);
    }

    public function testSyncNewOrdersLogsInfoWhenNoExternalIdsFound(): void
    {
        $context = Context::createDefaultContext();

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('upsert');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->with(false)->willReturn([
            'orders' => [
                ['externalId' => ''],
                ['orderNumber' => null],
                'invalid',
            ],
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $configService = $this->createConfigService('https://example.test/api/orders');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('External Orders sync finished: no external IDs found.');

        $service = new ExternalOrderSyncService($repository, $httpClient, $configService, $logger);

        $service->syncNewOrders($context);
    }

    public function testSyncNewOrdersUpsertsOrdersAndLogsTotals(): void
    {
        $context = Context::createDefaultContext();

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn($this->createExistingIdsResult($context, [
                'ext-1' => 'existing-id-1',
            ]));

        $repository->expects($this->once())
            ->method('upsert')
            ->with(
                $this->callback(function (array $payload): bool {
                    if (count($payload) !== 2) {
                        return false;
                    }

                    $byExternalId = [];
                    foreach ($payload as $row) {
                        $byExternalId[$row['externalId']] = $row['id'];
                    }

                    return isset($byExternalId['ext-1'], $byExternalId['ext-2'])
                        && $byExternalId['ext-1'] === 'existing-id-1'
                        && $byExternalId['ext-2'] !== '';
                }),
                $context
            );

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->with(false)->willReturn([
            'orders' => [
                ['externalId' => 'ext-1', 'foo' => 'bar'],
                ['id' => 'ext-2', 'foo' => 'baz'],
            ],
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $configService = $this->createConfigService('https://example.test/api/orders');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                [
                    'External Orders sync batching orders.',
                    $this->callback(static fn (array $context): bool => ($context['total'] ?? null) === 2
                        && ($context['batchSize'] ?? null) === 250),
                ],
                [
                    'External Orders sync finished.',
                    $this->callback(static fn (array $context): bool => ($context['total'] ?? null) === 2
                        && ($context['batchSize'] ?? null) === 250),
                ]
            );

        $service = new ExternalOrderSyncService($repository, $httpClient, $configService, $logger);

        $service->syncNewOrders($context);
    }

    public function testSyncNewOrdersLogsErrorWhenHttpClientFails(): void
    {
        $context = Context::createDefaultContext();

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('upsert');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $response->method('getHeaders')->with(false)->willReturn([
            'x-correlation-id' => ['corr-123'],
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willThrowException(new TestHttpException($response));

        $configService = $this->createConfigService('https://example.test/api/orders?token=secret');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'External Orders sync failed while calling API.',
                $this->callback(static function (array $context): bool {
                    return ($context['status'] ?? null) === 500
                        && ($context['correlationId'] ?? null) === 'corr-123'
                        && ($context['url'] ?? null) === 'https://example.test/api/orders?token=%2A%2A%2A';
                })
            );

        $service = new ExternalOrderSyncService($repository, $httpClient, $configService, $logger);

        $service->syncNewOrders($context);
    }

    private function createConfigService(string $apiUrl): SystemConfigService
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')->willReturnMap([
            ['ExternalOrders.config.externalOrdersApiUrl', null, $apiUrl],
            ['ExternalOrders.config.externalOrdersApiToken', null, 'token-123'],
            ['ExternalOrders.config.externalOrdersTimeout', null, 2.5],
        ]);

        return $configService;
    }

    /**
     * @param array<string, string> $mapping
     */
    private function createExistingIdsResult(Context $context, array $mapping): EntitySearchResult
    {
        $entities = new ExternalOrderCollection();

        foreach ($mapping as $externalId => $id) {
            $entity = new ExternalOrderEntity();
            $entity->setId($id);
            $entity->setExternalId($externalId);
            $entity->setPayload([]);
            $entities->add($entity);
        }

        return new EntitySearchResult(
            ExternalOrderDefinition::ENTITY_NAME,
            $entities->count(),
            $entities,
            null,
            new Criteria(),
            $context
        );
    }
}

class TestHttpException extends \RuntimeException implements HttpExceptionInterface
{
    public function __construct(private readonly ResponseInterface $response)
    {
        parent::__construct('HTTP error');
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
