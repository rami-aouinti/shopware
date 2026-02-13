<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Tests\Integration;

use Doctrine\DBAL\Connection;
use LieferzeitenAdmin\Service\LieferzeitenOrderOverviewService;
use PHPUnit\Framework\TestCase;

class LieferzeitenIntegrationScenariosTest extends TestCase
{
    public function testOverviewAdvancedFiltersAreAppliedTogetherInGeneratedSql(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchOne')->willReturn('0');
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->callback(static function (string $sql): bool {
                    return str_contains($sql, 'p.business_date_from >= :businessDateFrom')
                        && str_contains($sql, 'p.business_date_to <= :businessDateEndTo')
                        && str_contains($sql, 'p.calculated_delivery_date >= :calculatedDeliveryDateFrom')
                        && str_contains($sql, 'EXISTS (')
                        && str_contains($sql, 'lieferzeiten_neuer_liefertermin_history');
                }),
                $this->anything(),
            )
            ->willReturn([]);

        $service = new LieferzeitenOrderOverviewService($connection);
        $service->listOrders(1, 25, 'status', 'ASC', [
            'businessDateFrom' => '2026-01-01',
            'businessDateEndTo' => '2026-01-31',
            'calculatedDeliveryDateFrom' => '2026-01-05',
            'neuerLieferterminFrom' => '2026-01-10',
            'neuerLieferterminTo' => '2026-01-20',
        ]);
    }
}
