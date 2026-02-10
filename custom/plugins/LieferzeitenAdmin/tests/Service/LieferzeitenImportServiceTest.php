<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use LieferzeitenAdmin\Service\Audit\AuditLogService;
use LieferzeitenAdmin\Service\BaseDateResolver;
use LieferzeitenAdmin\Service\BusinessDayDeliveryDateCalculator;
use LieferzeitenAdmin\Service\ChannelDateSettingsProvider;
use LieferzeitenAdmin\Service\LieferzeitenImportService;
use LieferzeitenAdmin\Service\Notification\NotificationEventService;
use LieferzeitenAdmin\Service\Reliability\IntegrationReliabilityService;
use LieferzeitenAdmin\Sync\Adapter\ChannelOrderAdapterRegistry;
use LieferzeitenAdmin\Sync\San6\San6Client;
use LieferzeitenAdmin\Sync\San6\San6MatchingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\Context;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use LieferzeitenAdmin\Entity\PaketEntity;

class LieferzeitenImportServiceTest extends TestCase
{
    public function testIsTestOrderRecognizesTestbestellungFlag(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isTestOrder');
        $method->setAccessible(true);

        static::assertTrue($method->invoke($service, [
            'detail' => [
                'additional' => [
                    'Testbestellung' => '1',
                ],
            ],
        ]));
    }

    /** @dataProvider provideTruthyIsTestOrderVariants */
    public function testIsTestOrderRecognizesAllTruthyVariants(array $payload): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isTestOrder');
        $method->setAccessible(true);

        static::assertTrue($method->invoke($service, $payload));
    }

    /**
     * @return iterable<string,array{0: array<string,mixed>}>
     */
    public static function provideTruthyIsTestOrderVariants(): iterable
    {
        yield 'top level Testbestellung' => [['Testbestellung' => 'ja']];
        yield 'top level testbestellung' => [['testbestellung' => 'x']];
        yield 'top level isTestOrder' => [['isTestOrder' => true]];
        yield 'top level testOrder' => [['testOrder' => 1]];
        yield 'top level test_order' => [['test_order' => 'yes']];
        yield 'top level isTest' => [['isTest' => 'true']];
        yield 'additional Testbestellung' => [['additional' => ['Testbestellung' => '1']]];
        yield 'additional testbestellung' => [['additional' => ['testbestellung' => 'y']]];
        yield 'additional isTestOrder' => [['additional' => ['isTestOrder' => 'ja']]];
        yield 'detail additional Testbestellung' => [['detail' => ['additional' => ['Testbestellung' => '1']]]];
        yield 'detail additional testbestellung' => [['detail' => ['additional' => ['testbestellung' => 'true']]]];
        yield 'detail additional isTestOrder' => [['detail' => ['additional' => ['isTestOrder' => 1]]]];
    }

    /** @dataProvider provideFalsyIsTestOrderVariants */
    public function testIsTestOrderRejectsFalsyVariants(array $payload): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isTestOrder');
        $method->setAccessible(true);

        static::assertFalse($method->invoke($service, $payload));
    }

    /**
     * @return iterable<string,array{0: array<string,mixed>}>
     */
    public static function provideFalsyIsTestOrderVariants(): iterable
    {
        yield 'no flag' => [['orderNumber' => 'SO-10001']];
        yield 'isTestOrder false' => [['isTestOrder' => false]];
        yield 'test_order zero string' => [['test_order' => '0']];
        yield 'isTestOrder null additional' => [['additional' => ['isTestOrder' => null]]];
        yield 'detail additional no' => [['detail' => ['additional' => ['testbestellung' => 'no']]]];
    }

    public function testIsTestOrderKeepsRegularOrders(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isTestOrder');
        $method->setAccessible(true);

        static::assertFalse($method->invoke($service, [
            'orderNumber' => 'SO-10001',
            'customerEmail' => 'buyer@example.org',
            'isTestOrder' => false,
        ]));
    }

    public function testMarkExistingOrderAsTestLogsMarkedAuditWhenFirstMarking(): void
    {
        $paketRepository = $this->createMock(EntityRepository::class);
        $auditLogService = $this->createMock(AuditLogService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $paketId = Uuid::randomHex();
        $existing = new PaketEntity();
        $existing->setUniqueIdentifier($paketId);
        $existing->setIsTestOrder(false);

        $paketRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createSearchResult($existing));

        $paketRepository->expects($this->once())
            ->method('upsert')
            ->with($this->callback(static fn (array $payload): bool => ($payload[0]['isTestOrder'] ?? null) === true));

        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Lieferzeiten order marked as test order during import.',
                $this->callback(static fn (array $context): bool => ($context['alreadyMarkedAsTest'] ?? null) === false)
            );

        $auditLogService->expects($this->once())
            ->method('log')
            ->with(
                'order_marked_test',
                'paket',
                $paketId,
                $this->isInstanceOf(Context::class),
                $this->callback(static fn (array $data): bool => ($data['alreadyMarkedAsTest'] ?? null) === false),
                'shopware'
            );

        $service = $this->createService($paketRepository, $auditLogService, $logger);
        $method = new \ReflectionMethod($service, 'markExistingOrderAsTest');
        $method->setAccessible(true);

        $method->invoke($service, 'EXT-1', ['orderNumber' => 'SO-1'], Context::createDefaultContext());
    }

    public function testMarkExistingOrderAsTestLogsRemarkedAuditWhenAlreadyMarked(): void
    {
        $paketRepository = $this->createMock(EntityRepository::class);
        $auditLogService = $this->createMock(AuditLogService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $paketId = Uuid::randomHex();
        $existing = new PaketEntity();
        $existing->setUniqueIdentifier($paketId);
        $existing->setIsTestOrder(true);

        $paketRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createSearchResult($existing));

        $paketRepository->expects($this->once())
            ->method('upsert');

        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Lieferzeiten order marked as test order during import.',
                $this->callback(static fn (array $context): bool => ($context['alreadyMarkedAsTest'] ?? null) === true)
            );

        $auditLogService->expects($this->once())
            ->method('log')
            ->with(
                'order_re_marked_test',
                'paket',
                $paketId,
                $this->isInstanceOf(Context::class),
                $this->callback(static fn (array $data): bool => ($data['alreadyMarkedAsTest'] ?? null) === true),
                'shopware'
            );

        $service = $this->createService($paketRepository, $auditLogService, $logger);
        $method = new \ReflectionMethod($service, 'markExistingOrderAsTest');
        $method->setAccessible(true);

        $method->invoke($service, 'EXT-2', ['orderNumber' => 'SO-2'], Context::createDefaultContext());
    }

    public function testIsCompletedStatus8UsesSan6TrackingMapping(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isCompletedStatus8');
        $method->setAccessible(true);

        static::assertTrue($method->invoke($service, [
            'parcels' => [
                ['trackingStatus' => 'paketshop_retire'],
                ['trackingStatus' => 'ablageort'],
                ['trackingStatus' => 'zugestellt'],
            ],
        ], []));

        static::assertFalse($method->invoke($service, [
            'parcels' => [
                ['trackingStatus' => 'paketshop_non_retire'],
                ['trackingStatus' => 'zugestellt'],
            ],
        ], []));

        static::assertFalse($method->invoke($service, [
            'parcels' => [
                ['trackingStatus' => 'retoure'],
            ],
        ], []));
    }

    public function testIsCompletedStatus8RequiresAllParcelsToBeClosed(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isCompletedStatus8');
        $method->setAccessible(true);

        static::assertFalse($method->invoke($service, [
            'parcels' => [
                ['trackingStatus' => 'zugestellt'],
                ['trackingStatus' => 'nicht_zustellbar'],
            ],
        ], []));
    }

    private function createSearchResult(PaketEntity $entity): EntitySearchResult
    {
        return new EntitySearchResult(
            'lieferzeiten_paket',
            1,
            new EntityCollection([$entity]),
            null,
            new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria(),
            Context::createDefaultContext()
        );
    }

    private function createService(
        ?EntityRepository $paketRepository = null,
        ?AuditLogService $auditLogService = null,
        ?LoggerInterface $logger = null,
    ): LieferzeitenImportService {
        return new LieferzeitenImportService(
            $paketRepository ?? $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(HttpClientInterface::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(ChannelOrderAdapterRegistry::class),
            $this->createMock(San6Client::class),
            $this->createMock(San6MatchingService::class),
            $this->createMock(BaseDateResolver::class),
            $this->createMock(ChannelDateSettingsProvider::class),
            $this->createMock(BusinessDayDeliveryDateCalculator::class),
            $this->createMock(LockFactory::class),
            $this->createMock(NotificationEventService::class),
            $this->createMock(IntegrationReliabilityService::class),
            $auditLogService ?? $this->createMock(AuditLogService::class),
            $logger ?? $this->createMock(LoggerInterface::class),
        );
    }
}
