<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use LieferzeitenAdmin\Service\Audit\AuditLogService;
use LieferzeitenAdmin\Service\BaseDateResolver;
use LieferzeitenAdmin\Service\BusinessDayDeliveryDateCalculator;
use LieferzeitenAdmin\Service\Integration\IntegrationContractValidator;
use LieferzeitenAdmin\Service\Status8TrackingMappingProvider;
use LieferzeitenAdmin\Service\ChannelDateSettingsProvider;
use LieferzeitenAdmin\Service\LieferzeitenImportService;
use LieferzeitenAdmin\Service\Notification\NotificationEventService;
use LieferzeitenAdmin\Service\Notification\NotificationTriggerCatalog;
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
use Symfony\Contracts\HttpClient\ResponseInterface;
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

    public function testIsCompletedStatus8ReturnsTrueWhenAllParcelsDelivered(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isCompletedStatus8');
        $method->setAccessible(true);

        static::assertTrue($method->invoke($service, [
            'parcels' => [
                ['trackingStatus' => 'zugestellt'],
                ['trackingStatus' => 'delivered'],
            ],
        ], []));
    }

    public function testIsCompletedStatus8ReturnsFalseWhenAtLeastOneParcelNotDelivered(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isCompletedStatus8');
        $method->setAccessible(true);

        static::assertFalse($method->invoke($service, [
            'parcels' => [
                ['trackingStatus' => 'zugestellt'],
                ['trackingStatus' => 'unterwegs'],
            ],
        ], []));
    }

    public function testIsCompletedStatus8SupportsInternalDeliveryWithoutTrackingViaSan6Flag(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isCompletedStatus8');
        $method->setAccessible(true);

        static::assertTrue($method->invoke($service, [], [
            'internalDeliveryCompleted' => true,
        ]));

        static::assertTrue($method->invoke($service, [], [
            'deliveryCompletionState' => 'internal_completed',
        ]));

        static::assertFalse($method->invoke($service, [], [
            'internalDeliveryCompleted' => false,
        ]));
    }

    public function testIsCompletedStatus8UsesCarrierSpecificMappingForDhlAndGls(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isCompletedStatus8');
        $method->setAccessible(true);

        static::assertFalse($method->invoke($service, [
            'carrier' => 'DHL',
            'parcels' => [
                ['trackingStatus' => 'paketshop_non_retire'],
            ],
        ], []));

        static::assertFalse($method->invoke($service, [
            'carrier' => 'GLS',
            'parcels' => [
                ['trackingStatus' => 'douane'],
            ],
        ], []));

        static::assertTrue($method->invoke($service, [
            'carrier' => 'GLS',
            'parcels' => [
                ['trackingStatus' => 'delivered'],
            ],
        ], []));
    }

    public function testIsClosedParcelStatusForStatus8UsesDbOverrideVersionedMapping(): void
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->method('get')->willReturnMap([
            ['LieferzeitenAdmin.config.status8CarrierMapping', null, '{"version":2,"global":{"custom_state":true},"carriers":{"dhl":{"paketshop_non_retire":true}}}'],
        ]);

        $provider = new Status8TrackingMappingProvider($config);
        $service = $this->createService(status8TrackingMappingProvider: $provider, config: $config);
        $method = new \ReflectionMethod($service, 'isClosedParcelStatusForStatus8');
        $method->setAccessible(true);

        static::assertTrue($method->invoke($service, ['trackingStatus' => 'paketshop_non_retire'], ['carrier' => 'DHL']));
        static::assertTrue($method->invoke($service, ['trackingStatus' => 'custom-state'], ['carrier' => 'GLS']));
    }

    public function testIsSan6Status7SupportsStringAndBooleanMappings(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isSan6Status7');
        $method->setAccessible(true);

        static::assertTrue($method->invoke($service, ['san6Status' => 'approved'], []));
        static::assertTrue($method->invoke($service, [], ['status' => 'bereit']));
        static::assertTrue($method->invoke($service, ['san6Ready' => true], []));
        static::assertFalse($method->invoke($service, ['san6Ready' => false], ['status' => 'in_progress']));
    }

    public function testExtractTrackingNumbersFiltersPlaceholdersAndNormalizesVariants(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'extractTrackingNumbers');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'tracking_numbers' => ['  ', 'N/A', 'ABC-123'],
            'trackingNumber' => 'XYZ-987',
            'parcels' => [
                ['trackingNumber' => 'ohne tracking'],
                ['trackingNumber' => 'ABC-123'],
            ],
        ]);

        static::assertSame(['ABC-123', 'XYZ-987'], $result);
    }

    public function testIsInternalShipmentRecognizesDedicatedBusinessIndicators(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isInternalShipment');
        $method->setAccessible(true);

        static::assertTrue($method->invoke($service, ['internalShipment' => true]));
        static::assertTrue($method->invoke($service, ['shippingAssignmentType' => 'eigenversand']));
        static::assertTrue($method->invoke($service, ['carrier' => 'first medical']));
    }

    public function testIsInternalShipmentFallsBackWhenNoExternalTrackingExists(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'isInternalShipment');
        $method->setAccessible(true);

        static::assertTrue($method->invoke($service, [
            'trackingNumbers' => ['', 'N/A'],
            'parcels' => [
                ['trackingNumber' => 'ohne tracking'],
            ],
        ]));

        static::assertFalse($method->invoke($service, [
            'trackingNumbers' => ['DHL-12345'],
        ]));
    }

    public function testEnqueueStatusPushIsIdempotentForSamePendingStatus(): void
    {
        $service = $this->createService();
        $method = new \ReflectionMethod($service, 'enqueueStatusPush');
        $method->setAccessible(true);

        $queue = [[
            'targetStatus' => 7,
            'reason' => 'already pending',
            'attempts' => 0,
            'state' => 'pending',
            'nextAttemptAt' => date(DATE_ATOM),
            'createdAt' => date(DATE_ATOM),
        ]];

        $result = $method->invoke($service, $queue, 7, 'duplicate request');

        static::assertCount(1, $result);
        static::assertSame('already pending', $result[0]['reason']);
    }

    public function testProcessPendingStatusPushQueueMarksItemSentOnSuccessfulPush(): void
    {
        $context = Context::createDefaultContext();

        $entity = new PaketEntity();
        $entity->setUniqueIdentifier(Uuid::randomHex());
        $entity->setSourceSystem('shopware');
        $entity->setExternalOrderId('EXT-700');
        $entity->setPaketNumber('PK-700');
        $entity->setStatusPushQueue([[
            'targetStatus' => 7,
            'reason' => 'status7 mapped',
            'attempts' => 0,
            'state' => 'pending',
            'nextAttemptAt' => date(DATE_ATOM, time() - 60),
            'createdAt' => date(DATE_ATOM, time() - 120),
        ]]);

        $paketRepository = $this->createMock(EntityRepository::class);
        $paketRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createSearchResult($entity));

        $paketRepository->expects($this->once())
            ->method('upsert')
            ->with($this->callback(static function (array $payload): bool {
                $queue = $payload[0]['statusPushQueue'] ?? [];

                return ($queue[0]['state'] ?? null) === 'sent'
                    && isset($queue[0]['sentAt']);
            }), $context);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $config = $this->createMock(SystemConfigService::class);
        $config->method('get')->willReturnMap([
            ['LieferzeitenAdmin.config.shopwareStatusPushApiUrl', null, 'https://example.test/push'],
            ['LieferzeitenAdmin.config.shopwareStatusPushApiToken', null, 'secret'],
            ['LieferzeitenAdmin.config.gambioStatusPushApiUrl', null, ''],
            ['LieferzeitenAdmin.config.gambioStatusPushApiToken', null, ''],
        ]);

        $service = $this->createService($paketRepository, null, null, $httpClient, $config);
        $method = new \ReflectionMethod($service, 'processPendingStatusPushQueue');
        $method->setAccessible(true);

        $method->invoke($service, $context);
    }


    public function testResolveAndApplyBusinessDatesUsesPaymentDateForPrepaymentShippingAndDeliveryDates(): void
    {
        $settingsProvider = $this->createMock(ChannelDateSettingsProvider::class);
        $settingsProvider->method('getForChannel')->with('shopware')->willReturn([
            'shipping' => 1,
            'delivery' => 3,
        ]);

        $calculator = new BusinessDayDeliveryDateCalculator();
        $service = $this->createService(
            baseDateResolver: new BaseDateResolver(),
            settingsProvider: $settingsProvider,
            deliveryDateCalculator: $calculator,
        );

        $method = new \ReflectionMethod($service, 'resolveAndApplyBusinessDates');
        $method->setAccessible(true);

        [$result, $resolution] = $method->invoke($service, [
            'paymentMethod' => 'Vorkasse',
            'orderDate' => '2026-02-02 09:00:00',
            'paymentDate' => '2026-02-05 10:00:00',
        ], 'shopware');

        static::assertSame('payment_date', $resolution['baseDateType']);
        static::assertSame('2026-02-06T10:00:00+00:00', $result['shippingDate']);
        static::assertSame('2026-02-10T10:00:00+00:00', $result['deliveryDate']);
        static::assertSame($result['calculatedDeliveryDate'], $result['deliveryDate']);
    }

    public function testResolveAndApplyBusinessDatesFallsBackToOrderDateForPrepaymentWithoutPaymentDate(): void
    {
        $settingsProvider = $this->createMock(ChannelDateSettingsProvider::class);
        $settingsProvider->method('getForChannel')->with('shopware')->willReturn([
            'shipping' => 1,
            'delivery' => 2,
        ]);

        $calculator = new BusinessDayDeliveryDateCalculator();
        $service = $this->createService(
            baseDateResolver: new BaseDateResolver(),
            settingsProvider: $settingsProvider,
            deliveryDateCalculator: $calculator,
        );

        $method = new \ReflectionMethod($service, 'resolveAndApplyBusinessDates');
        $method->setAccessible(true);

        [$result, $resolution] = $method->invoke($service, [
            'paymentMethod' => 'prepayment',
            'orderDate' => '2026-02-02 09:00:00',
            'paymentDate' => null,
        ], 'shopware');

        static::assertSame('order_date_fallback', $resolution['baseDateType']);
        static::assertTrue($resolution['missingPaymentDate']);
        static::assertSame('2026-02-03T09:00:00+00:00', $result['shippingDate']);
        static::assertSame('2026-02-04T09:00:00+00:00', $result['deliveryDate']);
    }
    public function testProcessPendingStatusPushQueueAppliesRetryBackoffOnFailure(): void
    {
        $context = Context::createDefaultContext();
        $startedAt = time();

        $entity = new PaketEntity();
        $entity->setUniqueIdentifier(Uuid::randomHex());
        $entity->setSourceSystem('shopware');
        $entity->setExternalOrderId('EXT-800');
        $entity->setPaketNumber('PK-800');
        $entity->setStatusPushQueue([[
            'targetStatus' => 8,
            'reason' => 'status8 mapped',
            'attempts' => 0,
            'state' => 'pending',
            'nextAttemptAt' => date(DATE_ATOM, $startedAt - 60),
            'createdAt' => date(DATE_ATOM, $startedAt - 120),
        ]]);

        $paketRepository = $this->createMock(EntityRepository::class);
        $paketRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createSearchResult($entity));

        $paketRepository->expects($this->once())
            ->method('upsert')
            ->with($this->callback(static function (array $payload) use ($startedAt): bool {
                $queue = $payload[0]['statusPushQueue'] ?? [];
                $nextAttempt = strtotime((string) ($queue[0]['nextAttemptAt'] ?? '')) ?: 0;

                return ($queue[0]['state'] ?? null) === 'pending'
                    && ($queue[0]['attempts'] ?? null) === 1
                    && isset($queue[0]['lastErrorAt'])
                    && $nextAttempt >= $startedAt + 115
                    && $nextAttempt <= $startedAt + 125;
            }), $context);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $config = $this->createMock(SystemConfigService::class);
        $config->method('get')->willReturnMap([
            ['LieferzeitenAdmin.config.shopwareStatusPushApiUrl', null, 'https://example.test/push'],
            ['LieferzeitenAdmin.config.shopwareStatusPushApiToken', null, 'secret'],
            ['LieferzeitenAdmin.config.gambioStatusPushApiUrl', null, ''],
            ['LieferzeitenAdmin.config.gambioStatusPushApiToken', null, ''],
        ]);

        $service = $this->createService($paketRepository, null, null, $httpClient, $config);
        $method = new \ReflectionMethod($service, 'processPendingStatusPushQueue');
        $method->setAccessible(true);

        $method->invoke($service, $context);
    }


    public function testEmitNotificationEventsDispatchesDeliveryDateAssignedWhenDateAppearsFirstTime(): void
    {
        $calls = [];
        $notificationEventService = $this->createMock(NotificationEventService::class);
        $notificationEventService->method('dispatch')->willReturnCallback(static function (string $eventKey, string $triggerKey, string $channel) use (&$calls): bool {
            $calls[] = [$eventKey, $triggerKey, $channel];

            return true;
        });

        $service = $this->createService(notificationEventService: $notificationEventService);
        $method = new \ReflectionMethod($service, 'emitNotificationEvents');
        $method->setAccessible(true);

        $method->invoke(
            $service,
            'EXT-100',
            'shopware',
            ['deliveryDate' => '2026-02-20 09:00:00'],
            [],
            null,
            null,
            2,
            Context::createDefaultContext()
        );

        $assigned = array_values(array_filter($calls, static fn (array $call): bool => $call[1] === 'livraison.date.attribuee'));
        static::assertCount(3, $assigned);
    }

    public function testEmitNotificationEventsDispatchesDeliveryDateUpdatedWhenDateChanges(): void
    {
        $existing = new PaketEntity();
        $existing->setDeliveryDate(new \DateTimeImmutable('2026-02-20 09:00:00'));

        $calls = [];
        $notificationEventService = $this->createMock(NotificationEventService::class);
        $notificationEventService->method('dispatch')->willReturnCallback(static function (string $eventKey, string $triggerKey, string $channel, array $payload) use (&$calls): bool {
            $calls[] = [$eventKey, $triggerKey, $channel, $payload];

            return true;
        });

        $service = $this->createService(notificationEventService: $notificationEventService);
        $method = new \ReflectionMethod($service, 'emitNotificationEvents');
        $method->setAccessible(true);

        $method->invoke(
            $service,
            'EXT-101',
            'shopware',
            ['deliveryDate' => '2026-02-21 09:00:00'],
            [],
            $existing,
            2,
            2,
            Context::createDefaultContext()
        );

        $updated = array_values(array_filter($calls, static fn (array $call): bool => $call[1] === 'livraison.date.modifiee'));
        static::assertCount(3, $updated);
        static::assertSame('2026-02-20 09:00:00', $updated[0][3]['previousDeliveryDate'] ?? null);
    }

    public function testEmitNotificationEventsDispatchesReviewReminderOnTransitionToStatus8Only(): void
    {
        $notificationEventService = $this->createMock(NotificationEventService::class);
        $calls = [];
        $notificationEventService->method('dispatch')->willReturnCallback(static function (string $eventKey, string $triggerKey) use (&$calls): bool {
            $calls[] = [$eventKey, $triggerKey];

            return true;
        });

        $service = $this->createService(notificationEventService: $notificationEventService);
        $method = new \ReflectionMethod($service, 'emitNotificationEvents');
        $method->setAccessible(true);

        $method->invoke(
            $service,
            'EXT-102',
            'shopware',
            [],
            [],
            null,
            7,
            8,
            Context::createDefaultContext()
        );

        $method->invoke(
            $service,
            'EXT-103',
            'shopware',
            [],
            [],
            null,
            8,
            8,
            Context::createDefaultContext()
        );

        $reviewReminderCalls = array_values(array_filter($calls, static fn (array $call): bool => $call[1] === 'commande.terminee.rappel_evaluation'));
        static::assertCount(1, $reviewReminderCalls);
        static::assertStringStartsWith('order-completed-review-reminder:EXT-102:', $reviewReminderCalls[0][0]);
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
        ?HttpClientInterface $httpClient = null,
        ?SystemConfigService $config = null,
        ?Status8TrackingMappingProvider $status8TrackingMappingProvider = null,
        ?NotificationEventService $notificationEventService = null,
    ): LieferzeitenImportService {
        $config ??= $this->createMock(SystemConfigService::class);

        return new LieferzeitenImportService(
            $paketRepository ?? $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $httpClient ?? $this->createMock(HttpClientInterface::class),
            $config,
            $this->createMock(ChannelOrderAdapterRegistry::class),
            $this->createMock(San6Client::class),
            $this->createMock(San6MatchingService::class),
            $baseDateResolver ?? $this->createMock(BaseDateResolver::class),
            $settingsProvider ?? $this->createMock(ChannelDateSettingsProvider::class),
            $deliveryDateCalculator ?? $this->createMock(BusinessDayDeliveryDateCalculator::class),
            $status8TrackingMappingProvider ?? new Status8TrackingMappingProvider($config),
            $this->createMock(LockFactory::class),
            $notificationEventService ?? $this->createMock(NotificationEventService::class),
            $this->createMock(IntegrationReliabilityService::class),
            $this->createMock(IntegrationContractValidator::class),
            $auditLogService ?? $this->createMock(AuditLogService::class),
            $logger ?? $this->createMock(LoggerInterface::class),
        );
    }
}
