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
        $logger = new InMemoryLogger();

        $service = new ExternalOrderSyncService($repository, $httpClient, $configService, $logger);

        $service->syncNewOrders($context);

        static::assertTrue($logger->hasRecord('warning', 'External Orders sync skipped: API response does not contain orders.', [
            'channel' => 'b2b',
        ]));
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
        $logger = new InMemoryLogger();

        $service = new ExternalOrderSyncService($repository, $httpClient, $configService, $logger);

        $service->syncNewOrders($context);

        static::assertTrue($logger->hasRecord('info', 'External Orders sync finished: no external IDs found.', [
            'channel' => 'b2b',
        ]));
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
                    if (count($payload) !== 1) {
                        return false;
                    }

                    $row = $payload[0] ?? [];

                    return ($row['externalId'] ?? null) === 'ext-2'
                        && ($row['id'] ?? '') !== '';
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

        $logger = new InMemoryLogger();

        $service = new ExternalOrderSyncService($repository, $httpClient, $configService, $logger);

        $service->syncNewOrders($context);

        static::assertTrue($logger->hasRecord('info', 'External Orders sync finished.', [
            'channel' => 'b2b',
            'total' => 1,
        ]));
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

        $logger = new InMemoryLogger();

        $service = new ExternalOrderSyncService($repository, $httpClient, $configService, $logger);

        $service->syncNewOrders($context);

        static::assertTrue($logger->hasRecord('error', 'External Orders sync failed while calling API.', [
            'channel' => 'b2b',
            'status' => 500,
            'correlationId' => 'corr-123',
            'url' => 'https://example.test/api/orders?token=%2A%2A%2A',
        ]));
    }

    private function createConfigService(string $apiUrl): SystemConfigService
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('get')->willReturnMap([
            ['ExternalOrders.config.externalOrdersApiUrlB2b', null, $apiUrl],
            ['ExternalOrders.config.externalOrdersApiTokenB2b', null, 'token-123'],
            ['ExternalOrders.config.externalOrdersApiUrlEbayDe', null, ''],
            ['ExternalOrders.config.externalOrdersApiTokenEbayDe', null, ''],
            ['ExternalOrders.config.externalOrdersApiUrlKaufland', null, ''],
            ['ExternalOrders.config.externalOrdersApiTokenKaufland', null, ''],
            ['ExternalOrders.config.externalOrdersApiUrlEbayAt', null, ''],
            ['ExternalOrders.config.externalOrdersApiTokenEbayAt', null, ''],
            ['ExternalOrders.config.externalOrdersApiUrlZonami', null, ''],
            ['ExternalOrders.config.externalOrdersApiTokenZonami', null, ''],
            ['ExternalOrders.config.externalOrdersApiUrlPeg', null, ''],
            ['ExternalOrders.config.externalOrdersApiTokenPeg', null, ''],
            ['ExternalOrders.config.externalOrdersApiUrlBezb', null, ''],
            ['ExternalOrders.config.externalOrdersApiTokenBezb', null, ''],
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

final class InMemoryLogger implements LoggerInterface
{
    /**
     * @var array<int, array{level: string, message: string, context: array<mixed>}>
     */
    public array $records = [];

    public function emergency($message, array $context = []): void
    {
        $this->log('emergency', (string) $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log('alert', (string) $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log('critical', (string) $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log('error', (string) $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log('warning', (string) $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log('notice', (string) $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log('info', (string) $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log('debug', (string) $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @param array<string, mixed> $contextSubset
     */
    public function hasRecord(string $level, string $message, array $contextSubset = []): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] !== $level || $record['message'] !== $message) {
                continue;
            }

            $matches = true;
            foreach ($contextSubset as $key => $value) {
                if (!array_key_exists($key, $record['context']) || $record['context'][$key] !== $value) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return true;
            }
        }

        return false;
    }
}
