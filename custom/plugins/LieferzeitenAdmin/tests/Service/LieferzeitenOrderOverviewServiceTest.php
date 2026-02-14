<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Service;

use Doctrine\DBAL\Connection;
use LieferzeitenAdmin\Service\LieferzeitenOrderOverviewService;
use PHPUnit\Framework\TestCase;

class LieferzeitenOrderOverviewServiceTest extends TestCase
{
    public function testListOrdersExcludesTestOrdersInWhereClause(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, 'COALESCE(p.is_test_order, 0) = 0')),
                $this->anything()
            )
            ->willReturn('0');

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, 'COALESCE(p.is_test_order, 0) = 0')),
                $this->anything()
            )
            ->willReturn([]);

        $service = new LieferzeitenOrderOverviewService($connection);

        $result = $service->listOrders();

        static::assertSame(0, $result['total']);
    }

    public function testListOrdersKeepsTestOrderExclusionWithoutFilters(): void
    {
        $capturedSql = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')
            ->willReturnCallback(static function (string $sql) use (&$capturedSql): string {
                $capturedSql[] = $sql;

                return '0';
            });

        $connection->method('fetchAllAssociative')
            ->willReturnCallback(static function (string $sql) use (&$capturedSql): array {
                $capturedSql[] = $sql;

                return [];
            });

        $service = new LieferzeitenOrderOverviewService($connection);
        $service->listOrders();

        static::assertCount(2, $capturedSql);
        foreach ($capturedSql as $sql) {
            static::assertStringContainsString('COALESCE(p.is_test_order, 0) = 0', $sql);
        }
    }

    public function testListOrdersKeepsTestOrderExclusionWithMultipleFilters(): void
    {
        $capturedSql = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')
            ->willReturnCallback(static function (string $sql) use (&$capturedSql): string {
                $capturedSql[] = $sql;

                return '0';
            });

        $connection->method('fetchAllAssociative')
            ->willReturnCallback(static function (string $sql) use (&$capturedSql): array {
                $capturedSql[] = $sql;

                return [];
            });

        $service = new LieferzeitenOrderOverviewService($connection);
        $service->listOrders(1, 25, 'orderDate', 'DESC', [
            'bestellnummer' => 'SO-',
            'status' => '2',
            'sendenummer' => 'TRACK',
            'shippingAssignmentType' => 'standard',
            'orderDateFrom' => '2026-02-01',
            'orderDateTo' => '2026-02-10',
        ]);

        static::assertCount(2, $capturedSql);
        foreach ($capturedSql as $sql) {
            static::assertStringContainsString('COALESCE(p.is_test_order, 0) = 0', $sql);
            static::assertStringContainsString('p.external_order_id LIKE :bestellnummer', $sql);
            static::assertStringContainsString('p.status LIKE :status', $sql);
        }
    }

    public function testListOrdersAddsNewRangeFiltersAndSecondarySorts(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('0');

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->callback(static function (string $sql): bool {
                    return str_contains($sql, 'p.payment_date >= :paymentDateFrom')
                        && str_contains($sql, 'p.calculated_delivery_date <= :calculatedDeliveryDateTo')
                        && str_contains($sql, 'FROM `lieferzeiten_liefertermin_lieferant_history` latest')
                        && str_contains($sql, 'FROM `lieferzeiten_neuer_liefertermin_paket_history` latest')
                        && str_contains($sql, 'ORDER BY p.shipping_date ASC, p.order_date DESC, p.id DESC');
                }),
                $this->callback(static function (array $params): bool {
                    return isset($params['paymentDateFrom'], $params['calculatedDeliveryDateTo'], $params['lieferterminLieferantFrom'], $params['neuerLieferterminTo']);
                })
            )
            ->willReturn([]);

        $service = new LieferzeitenOrderOverviewService($connection);

        $service->listOrders(1, 25, 'spaetesterVersand', 'ASC', [
            'paymentDateFrom' => '2026-01-01',
            'calculatedDeliveryDateTo' => '2026-01-31',
            'lieferterminLieferantFrom' => '2026-01-05',
            'neuerLieferterminTo' => '2026-01-20',
        ]);
    }


    public function testListOrdersAddsBusinessStatusPayload(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('1');

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 'abc',
                    'status' => '2',
                    'orderedQuantityTotal' => '5',
                    'shippedQuantityTotal' => '3',
                ],
            ]);

        $service = new LieferzeitenOrderOverviewService($connection);

        $result = $service->listOrders();

        static::assertSame([
            'code' => '2',
            'label' => 'In clarification',
        ], $result['data'][0]['businessStatus']);
        static::assertSame('5', $result['data'][0]['orderedQuantityTotal']);
        static::assertSame('3', $result['data'][0]['shippedQuantityTotal']);
    }


    public function testListOrdersSelectsCustomerNameColumns(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('0');

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->callback(static function (string $sql): bool {
                    return str_contains($sql, 'p.customer_first_name AS customerFirstName')
                        && str_contains($sql, 'p.customer_last_name AS customerLastName')
                        && str_contains($sql, 'p.customer_additional_name AS customerAdditionalName');
                }),
                $this->anything()
            )
            ->willReturn([]);

        $service = new LieferzeitenOrderOverviewService($connection);
        $service->listOrders();
    }

    public function testSortWhitelistFallsBackToOrderDate(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('0');

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->callback(static fn (string $sql): bool => str_contains($sql, 'ORDER BY p.order_date DESC, p.id DESC')),
                $this->anything()
            )
            ->willReturn([]);

        $service = new LieferzeitenOrderOverviewService($connection);

        $result = $service->listOrders(1, 25, 'DROP TABLE', 'DESC');

        static::assertContains('san6Pos', $result['nonFilterableFields']);
        static::assertContains('comment', $result['nonFilterableFields']);
        static::assertContains('lieferterminLieferantFrom', $result['filterableFields']);
        static::assertContains('neuerLieferterminTo', $result['filterableFields']);
    }

    public function testGetOrderDetailsReturnsStructuredDetails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')
            ->willReturn([
                'id' => 'paket-1',
                'status' => '2',
            ]);

        $connection->method('fetchAllAssociative')
            ->willReturnCallback(static function (string $sql): array {
                if (str_contains($sql, 'FROM `lieferzeiten_position` pos')) {
                    return [[
                        'id' => 'position-1',
                        'paketId' => 'paket-1',
                        'number' => '10',
                        'label' => 'ART-1',
                        'quantity' => '1',
                        'orderedQuantity' => '3',
                        'shippedQuantity' => '2',
                        'status' => 'open',
                        'updatedAt' => '2026-01-01 10:00:00',
                        'currentComment' => 'commentaire',
                    ]];
                }

                if (str_contains($sql, 'FROM `lieferzeiten_sendenummer_history` sh')) {
                    return [[
                        'positionId' => 'position-1',
                        'number' => 'TRACK-1',
                    ]];
                }

                if (str_contains($sql, 'FROM `lieferzeiten_neuer_liefertermin_paket_history` nph')) {
                    return [[
                        'paketId' => 'paket-1',
                        'fromDate' => '2026-01-02 00:00:00',
                        'toDate' => '2026-01-03 00:00:00',
                        'lastChangedBy' => 'tester',
                        'lastChangedAt' => '2026-01-01 12:00:00',
                    ]];
                }

                if (str_contains($sql, 'FROM `lieferzeiten_liefertermin_lieferant_history` llh')) {
                    return [[
                        'paketId' => 'paket-1',
                        'fromDate' => '2026-01-01 00:00:00',
                        'toDate' => '2026-01-05 00:00:00',
                        'lastChangedBy' => 'tester',
                        'lastChangedAt' => '2026-01-01 11:00:00',
                    ]];
                }

                if (str_contains($sql, 'latest_range.liefertermin_from')) {
                    return [[
                        'id' => 'paket-1',
                        'paketId' => 'paket-1',
                        'status' => '2',
                        'closed' => 0,
                        'neuerLieferterminFrom' => '2026-01-02 00:00:00',
                        'neuerLieferterminTo' => '2026-01-03 00:00:00',
                    ]];
                }

                return [];
            });

        $service = new LieferzeitenOrderOverviewService($connection);

        $result = $service->getOrderDetails('f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1');

        static::assertIsArray($result);
        static::assertArrayHasKey('positions', $result);
        static::assertArrayHasKey('parcels', $result);
        static::assertArrayHasKey('lieferterminLieferantHistory', $result);
        static::assertArrayHasKey('neuerLieferterminHistory', $result);
        static::assertArrayHasKey('commentHistory', $result);
        static::assertSame('3', $result['positions'][0]['orderedQuantity']);
        static::assertSame('2', $result['positions'][0]['shippedQuantity']);
    }
}
