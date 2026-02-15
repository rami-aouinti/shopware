<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use LieferzeitenAdmin\Service\Tracking\TrackingDeliveryDateSyncService;
use LieferzeitenAdmin\Service\Tracking\TrackingHistoryService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;

class TrackingDeliveryDateSyncServiceIntegrationTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['url' => 'sqlite:///:memory:']);
        $this->createSchema();
    }

    public function testSyncSetsLatestTerminalDatePerPackageAndPropagatesToOrder(): void
    {
        $this->seedPackage('paket-1', 'ORDER-1', null);
        $this->seedPackage('paket-2', 'ORDER-1', null);

        $this->seedPositionWithTracking('pos-1', 'paket-1', 'TRK-1');
        $this->seedPositionWithTracking('pos-2', 'paket-2', 'TRK-2');

        $trackingHistoryService = $this->createMock(TrackingHistoryService::class);
        $trackingHistoryService->method('fetchHistory')->willReturnCallback(static function (string $carrier, string $trackingNumber): array {
            if ($carrier !== 'dhl') {
                return ['ok' => false];
            }

            return match ($trackingNumber) {
                'TRK-1' => [
                    'ok' => true,
                    'events' => [
                        ['status' => 'delivered', 'timestamp' => '2026-02-10T10:00:00+00:00'],
                        ['status' => 'completed', 'timestamp' => '2026-02-11T12:00:00+00:00'],
                    ],
                ],
                'TRK-2' => [
                    'ok' => true,
                    'events' => [
                        ['status' => 'in_transit', 'timestamp' => '2026-02-12T07:00:00+00:00'],
                        ['status' => 'delivered', 'timestamp' => '2026-02-12T09:00:00+00:00'],
                    ],
                ],
                default => ['ok' => true, 'events' => []],
            };
        });

        $service = new TrackingDeliveryDateSyncService($this->connection, $trackingHistoryService, new NullLogger(), ['dhl']);
        $service->sync(Context::createDefaultContext());

        $rows = $this->connection->fetchAllAssociative('SELECT id, delivery_date FROM lieferzeiten_paket WHERE external_order_id = :orderId ORDER BY id', ['orderId' => 'ORDER-1']);

        static::assertCount(2, $rows);
        static::assertSame('2026-02-12T09:00:00+00:00', $rows[0]['delivery_date']);
        static::assertSame('2026-02-12T09:00:00+00:00', $rows[1]['delivery_date']);
    }

    public function testSyncDoesNotPropagateIfOrderContainsUndeliveredPackage(): void
    {
        $this->seedPackage('paket-3', 'ORDER-2', null);
        $this->seedPackage('paket-4', 'ORDER-2', null);

        $this->seedPositionWithTracking('pos-3', 'paket-3', 'TRK-3');
        $this->seedPositionWithTracking('pos-4', 'paket-4', 'TRK-4');

        $trackingHistoryService = $this->createMock(TrackingHistoryService::class);
        $trackingHistoryService->method('fetchHistory')->willReturnCallback(static function (string $carrier, string $trackingNumber): array {
            if ($carrier !== 'dhl') {
                return ['ok' => false];
            }

            return match ($trackingNumber) {
                'TRK-3' => ['ok' => true, 'events' => [['status' => 'delivered', 'timestamp' => '2026-02-15T08:00:00+00:00']]],
                'TRK-4' => ['ok' => true, 'events' => [['status' => 'in_transit', 'timestamp' => '2026-02-15T09:00:00+00:00']]],
                default => ['ok' => true, 'events' => []],
            };
        });

        $service = new TrackingDeliveryDateSyncService($this->connection, $trackingHistoryService, new NullLogger(), ['dhl']);
        $service->sync(Context::createDefaultContext());

        $deliveredDate = $this->connection->fetchOne('SELECT delivery_date FROM lieferzeiten_paket WHERE id = :id', ['id' => 'paket-3']);
        $undeliveredDate = $this->connection->fetchOne('SELECT delivery_date FROM lieferzeiten_paket WHERE id = :id', ['id' => 'paket-4']);

        static::assertSame('2026-02-15T08:00:00+00:00', $deliveredDate);
        static::assertNull($undeliveredDate);
    }

    private function createSchema(): void
    {
        $this->connection->executeStatement('CREATE TABLE lieferzeiten_paket (id TEXT PRIMARY KEY, external_order_id TEXT, delivery_date TEXT)');
        $this->connection->executeStatement('CREATE TABLE lieferzeiten_position (id TEXT PRIMARY KEY, paket_id TEXT)');
        $this->connection->executeStatement('CREATE TABLE lieferzeiten_sendenummer_history (id TEXT PRIMARY KEY, position_id TEXT, sendenummer TEXT)');
    }

    private function seedPackage(string $id, string $externalOrderId, ?string $deliveryDate): void
    {
        $this->connection->insert('lieferzeiten_paket', [
            'id' => $id,
            'external_order_id' => $externalOrderId,
            'delivery_date' => $deliveryDate,
        ]);
    }

    private function seedPositionWithTracking(string $positionId, string $paketId, string $trackingNumber): void
    {
        $this->connection->insert('lieferzeiten_position', [
            'id' => $positionId,
            'paket_id' => $paketId,
        ]);

        $this->connection->insert('lieferzeiten_sendenummer_history', [
            'id' => $positionId . '-trk',
            'position_id' => $positionId,
            'sendenummer' => $trackingNumber,
        ]);
    }
}
