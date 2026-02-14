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
                        && str_contains($sql, 'FROM `lieferzeiten_neuer_liefertermin_history` latest')
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
                ],
            ]);

        $service = new LieferzeitenOrderOverviewService($connection);

        $result = $service->listOrders();

        static::assertSame([
            'code' => '2',
            'label' => 'In clarification',
        ], $result['data'][0]['businessStatus']);
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
}
