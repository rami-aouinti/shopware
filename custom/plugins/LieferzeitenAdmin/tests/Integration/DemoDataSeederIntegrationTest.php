<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use LieferzeitenAdmin\Service\DemoDataSeederService;
use LieferzeitenAdmin\Service\ChannelDateSettingsProvider;
use LieferzeitenAdmin\Service\ChannelPdmsThresholdResolver;
use LieferzeitenAdmin\Service\LieferzeitenOrderOverviewService;
use LieferzeitenAdmin\Service\LieferzeitenStatisticsService;
use LieferzeitenAdmin\Service\LieferzeitenExternalOrderLinkService;
use ExternalOrders\Service\ExternalOrderTestDataService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class DemoDataSeederIntegrationTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'url' => 'sqlite:///:memory:',
        ]);

        $this->createSchema($this->connection);
    }

    public function testDemoDataCreationIsSuccessful(): void
    {
        $seeder = $this->createSeeder();

        $result = $seeder->seed(Context::createDefaultContext(), false);

        static::assertSame('ok', $result['status']);
        static::assertGreaterThan(0, $result['created']['paket']);
        static::assertGreaterThan(0, $result['created']['position']);
        static::assertGreaterThan(0, $result['created']['notificationEvents']);
        static::assertGreaterThan(0, $result['created']['taskAssignmentRules']);
        static::assertSame([], $result['linking']['missingIds']);
        static::assertSame(9, $result['linking']['linked']);
        static::assertSame(0, $result['linking']['deletedCount']);
        static::assertFalse($result['linking']['destructiveCleanup']);
    }

    public function testRerunDoesNotCreateDuplicates(): void
    {
        $seeder = $this->createSeeder();

        $seeder->seed(Context::createDefaultContext(), false);
        $firstCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM lieferzeiten_paket');

        $seeder->seed(Context::createDefaultContext(), false);
        $secondCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM lieferzeiten_paket');

        static::assertSame($firstCount, $secondCount);

        $duplicates = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM (
                SELECT external_order_id
                FROM lieferzeiten_paket
                GROUP BY external_order_id
                HAVING COUNT(*) > 1
            ) x'
        );

        static::assertSame(0, $duplicates);
    }

    public function testResetThenReseedWorks(): void
    {
        $seeder = $this->createSeeder();
        $seeder->seed(Context::createDefaultContext(), false);

        $result = $seeder->seed(Context::createDefaultContext(), true);

        static::assertTrue($result['reset']);
        static::assertGreaterThan(0, array_sum($result['deleted']));
        static::assertSame(9, $result['created']['paket']);
    }


    public function testLinkingKeepsValidPaketeAndLinksAllGeneratedExternalOrders(): void
    {
        $externalIds = [
            'DEMO-B2B-001',
            'DEMO-EBAY_DE-001',
            'DEMO-KAUFLAND-001',
            'DEMO-EBAY_AT-001',
            'DEMO-ZONAMI-001',
            'DEMO-PEG-001',
            'DEMO-BEZB-001',
            'DEMO-B2B-002',
            'DEMO-EBAY_DE-002',
        ];

        foreach ($externalIds as $index => $externalId) {
            $this->connection->insert('lieferzeiten_paket', [
                'id' => random_bytes(16),
                'paket_number' => sprintf('PKT-%03d', $index + 1),
                'external_order_id' => $externalId,
                'source_system' => 'First Medical',
                'status' => '1',
                'shipping_assignment_type' => 'dhl',
                'partial_shipment_quantity' => null,
                'order_date' => '2026-01-01 00:00:00',
                'shipping_date' => '2026-01-02 00:00:00',
                'delivery_date' => '2026-01-03 00:00:00',
                'business_date_from' => '2026-01-02 00:00:00',
                'business_date_to' => '2026-01-03 00:00:00',
                'payment_date' => '2026-01-01 00:00:00',
                'calculated_delivery_date' => '2026-01-03 00:00:00',
                'is_test_order' => 0,
                'last_changed_by' => 'demo.seeder.run:previous',
                'last_changed_at' => '2026-01-01 00:00:00',
                'created_at' => '2026-01-01 00:00:00',
            ]);

            $this->connection->insert('external_order_data', [
                'id' => (string) ($index + 1),
                'external_id' => $externalId,
            ]);
        }

        $linkService = new LieferzeitenExternalOrderLinkService($this->connection, $this->createMock(\Psr\Log\LoggerInterface::class));
        $result = $linkService->linkDemoExternalOrders($externalIds);

        static::assertSame([], $result['missingIds']);
        static::assertSame(count($externalIds), $result['linked']);
        static::assertSame(0, $result['deletedCount']);
        static::assertFalse($result['destructiveCleanup']);
        static::assertSame(count($externalIds), (int) $this->connection->fetchOne('SELECT COUNT(*) FROM lieferzeiten_paket'));
    }


    public function testMissingIdsAreReportedWithoutCleanupByDefault(): void
    {
        $seedRunId = 'run-safe-mode';
        $seedMarker = 'demo.seeder.run:' . $seedRunId;
        $missingId = 'DEMO-MISSING-001';

        $this->insertPaketForExternalOrder($missingId, $seedMarker, 'PKT-MISS-CURRENT');
        $this->insertPaketForExternalOrder($missingId, 'demo.seeder.run:previous', 'PKT-MISS-PREV');
        $this->connection->insert('external_order_data', ['id' => '1', 'external_id' => 'DEMO-EXISTS-001']);

        $linkService = new LieferzeitenExternalOrderLinkService($this->connection, $this->createMock(\Psr\Log\LoggerInterface::class));
        $result = $linkService->linkDemoExternalOrders(['DEMO-EXISTS-001', $missingId], $seedRunId, $seedMarker);

        static::assertSame([$missingId], $result['missingIds']);
        static::assertSame(0, $result['deletedCount']);
        static::assertFalse($result['destructiveCleanup']);
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM lieferzeiten_paket WHERE external_order_id = ?', [$missingId]));
    }

    public function testDestructiveCleanupOnlyDeletesRowsFromCurrentRunMarker(): void
    {
        $seedRunId = 'run-destructive';
        $seedMarker = 'demo.seeder.run:' . $seedRunId;
        $missingId = 'DEMO-MISSING-002';

        $this->insertPaketForExternalOrder($missingId, $seedMarker, 'PKT-DEL-CURRENT');
        $this->insertPaketForExternalOrder($missingId, 'demo.seeder.run:previous', 'PKT-KEEP-PREVIOUS');
        $this->connection->insert('external_order_data', ['id' => '2', 'external_id' => 'DEMO-EXISTS-002']);

        $linkService = new LieferzeitenExternalOrderLinkService($this->connection, $this->createMock(\Psr\Log\LoggerInterface::class));
        $result = $linkService->linkDemoExternalOrders(['DEMO-EXISTS-002', $missingId], $seedRunId, $seedMarker, true);

        static::assertTrue($result['destructiveCleanup']);
        static::assertSame([$missingId], $result['missingIds']);
        static::assertSame(1, $result['deletedCount']);
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM lieferzeiten_paket WHERE external_order_id = ?', [$missingId]));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM lieferzeiten_paket WHERE external_order_id = ? AND last_changed_by = ?', [$missingId, 'demo.seeder.run:previous']));
    }

    public function testSeededDataIsVisibleInListingAndStatisticsEndpoints(): void
    {
        $seeder = $this->createSeeder();
        $seeder->seed(Context::createDefaultContext(), false);

        $orderOverviewService = new LieferzeitenOrderOverviewService($this->connection);
        $orders = $orderOverviewService->listOrders(1, 100, null, null, []);

        static::assertSame(8, $orders['total'], 'Test order must be excluded from listing.');

        $statisticsService = new LieferzeitenStatisticsService(
            $this->connection,
            new ChannelPdmsThresholdResolver(
                $this->connection,
                new ChannelDateSettingsProvider($this->createMock(SystemConfigService::class), $this->connection),
            ),
        );
        $stats = $statisticsService->getStatistics(30, null, null);

        static::assertGreaterThan(0, $stats['metrics']['openOrders']);
        static::assertGreaterThan(0, count($stats['channels']));

        $statusCoverage = $this->connection->fetchFirstColumn('SELECT DISTINCT status FROM lieferzeiten_paket WHERE is_test_order = 0 ORDER BY status');
        static::assertSame(['1', '2', '3', '4', '5', '6', '7', '8'], $statusCoverage);
    }


    private function createSeeder(): DemoDataSeederService
    {
        $externalIds = [
            'DEMO-B2B-001',
            'DEMO-EBAY_DE-001',
            'DEMO-KAUFLAND-001',
            'DEMO-EBAY_AT-001',
            'DEMO-ZONAMI-001',
            'DEMO-PEG-001',
            'DEMO-BEZB-001',
            'DEMO-B2B-002',
            'DEMO-EBAY_DE-002',
        ];

        $linkService = $this->createMock(LieferzeitenExternalOrderLinkService::class);
        $linkService
            ->method('linkDemoExternalOrders')
            ->with($externalIds)
            ->willReturn([
                'linked' => count($externalIds),
                'missingIds' => [],
                'deletedCount' => 0,
                'deletedMissingPackages' => 0,
                'destructiveCleanup' => false,
            ]);

        $externalOrderTestDataService = $this->createMock(ExternalOrderTestDataService::class);
        $externalOrderTestDataService
            ->method('getDemoExternalOrderIds')
            ->willReturn($externalIds);

        return new DemoDataSeederService($this->connection, $linkService, $externalOrderTestDataService);
    }


    private function insertPaketForExternalOrder(string $externalOrderId, string $lastChangedBy, string $paketNumber): void
    {
        $this->connection->insert('lieferzeiten_paket', [
            'id' => random_bytes(16),
            'paket_number' => $paketNumber,
            'external_order_id' => $externalOrderId,
            'source_system' => 'First Medical',
            'status' => '1',
            'shipping_assignment_type' => 'dhl',
            'partial_shipment_quantity' => null,
            'order_date' => '2026-01-01 00:00:00',
            'shipping_date' => '2026-01-02 00:00:00',
            'delivery_date' => '2026-01-03 00:00:00',
            'business_date_from' => '2026-01-02 00:00:00',
            'business_date_to' => '2026-01-03 00:00:00',
            'payment_date' => '2026-01-01 00:00:00',
            'calculated_delivery_date' => '2026-01-03 00:00:00',
            'is_test_order' => 0,
            'last_changed_by' => $lastChangedBy,
            'last_changed_at' => '2026-01-01 00:00:00',
            'created_at' => '2026-01-01 00:00:00',
        ]);
    }

    private function createSchema(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE lieferzeiten_paket (
            id BLOB PRIMARY KEY,
            paket_number TEXT,
            external_order_id TEXT,
            source_system TEXT,
            status TEXT,
            shipping_assignment_type TEXT,
            partial_shipment_quantity TEXT,
            order_date TEXT,
            shipping_date TEXT,
            delivery_date TEXT,
            business_date_from TEXT,
            business_date_to TEXT,
            payment_date TEXT,
            calculated_delivery_date TEXT,
            is_test_order INTEGER,
            last_changed_by TEXT,
            last_changed_at TEXT,
            created_at TEXT
        )');

        $connection->executeStatement('CREATE TABLE lieferzeiten_position (
            id BLOB PRIMARY KEY,
            paket_id BLOB,
            position_number TEXT,
            article_number TEXT,
            status TEXT,
            ordered_at TEXT,
            ordered_quantity INTEGER,
            shipped_quantity INTEGER,
            last_changed_by TEXT,
            last_changed_at TEXT,
            created_at TEXT
        )');

        $connection->executeStatement('CREATE TABLE lieferzeiten_liefertermin_lieferant_history (
            id BLOB PRIMARY KEY,
            position_id BLOB,
            liefertermin_from TEXT,
            liefertermin_to TEXT,
            created_at TEXT,
            last_changed_by TEXT,
            last_changed_at TEXT
        )');

        $connection->executeStatement('CREATE TABLE lieferzeiten_neuer_liefertermin_history (
            id BLOB PRIMARY KEY,
            position_id BLOB,
            liefertermin_from TEXT,
            liefertermin_to TEXT,
            created_at TEXT,
            last_changed_by TEXT,
            last_changed_at TEXT
        )');

        $connection->executeStatement('CREATE TABLE lieferzeiten_sendenummer_history (
            id BLOB PRIMARY KEY,
            position_id BLOB,
            sendenummer TEXT,
            created_at TEXT,
            last_changed_by TEXT,
            last_changed_at TEXT
        )');

        $connection->executeStatement('CREATE TABLE lieferzeiten_channel_settings (
            id BLOB PRIMARY KEY,
            sales_channel_id TEXT,
            default_status TEXT,
            enable_notifications INTEGER,
            shipping_working_days INTEGER,
            shipping_cutoff TEXT,
            delivery_working_days INTEGER,
            delivery_cutoff TEXT,
            last_changed_by TEXT,
            last_changed_at TEXT,
            created_at TEXT
        )');

        $connection->executeStatement('CREATE TABLE lieferzeiten_notification_toggle (
            id BLOB PRIMARY KEY,
            code TEXT,
            trigger_key TEXT,
            channel TEXT,
            enabled INTEGER,
            last_changed_by TEXT,
            last_changed_at TEXT,
            created_at TEXT
        )');

        $connection->executeStatement('CREATE TABLE lieferzeiten_notification_event (
            id BLOB PRIMARY KEY,
            event_key TEXT,
            trigger_key TEXT,
            channel TEXT,
            external_order_id TEXT,
            source_system TEXT,
            payload TEXT,
            status TEXT,
            created_at TEXT
        )');

        $connection->executeStatement('CREATE TABLE lieferzeiten_task_assignment_rule (
            id BLOB PRIMARY KEY,
            name TEXT,
            status TEXT,
            trigger_key TEXT,
            assignee_type TEXT,
            assignee_identifier TEXT,
            priority INTEGER,
            active INTEGER,
            conditions TEXT,
            last_changed_by TEXT,
            last_changed_at TEXT,
            created_at TEXT
        )');

        $connection->executeStatement('CREATE TABLE external_order_data (
            id TEXT PRIMARY KEY,
            external_id TEXT
        )');

        $connection->executeStatement('CREATE TABLE lieferzeiten_task (
            id BLOB PRIMARY KEY,
            status TEXT,
            assignee TEXT,
            due_date TEXT,
            initiator TEXT,
            payload TEXT,
            closed_at TEXT,
            created_at TEXT
        )');

        $connection->executeStatement('CREATE TABLE lieferzeiten_audit_log (
            id BLOB PRIMARY KEY,
            action TEXT,
            target_type TEXT,
            target_id TEXT,
            source_system TEXT,
            user_id TEXT,
            correlation_id TEXT,
            payload TEXT,
            created_at TEXT
        )');
    }
}
