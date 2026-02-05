<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service\Stats;

use Doctrine\DBAL\Connection;

class LieferzeitenStatsAggregationService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getStats(): array
    {
        $averageLeadTimeDays = $this->getAverageLeadTimeDays();
        $volume = $this->getVolume();
        [$overdueCount, $overdueTotal] = $this->getOverdueStats();
        $overdueRate = $overdueTotal > 0 ? round($overdueCount / $overdueTotal, 4) : null;

        return [
            'kpis' => [
                'averageLeadTimeDays' => $averageLeadTimeDays,
                'overdueRate' => $overdueRate,
                'volume' => $volume,
                'overdueCount' => $overdueCount,
                'overdueTotal' => $overdueTotal,
            ],
            'rows' => [
                [
                    'metric' => 'averageLeadTimeDays',
                    'value' => $averageLeadTimeDays,
                ],
                [
                    'metric' => 'overdueRate',
                    'value' => $overdueRate,
                ],
                [
                    'metric' => 'volume',
                    'value' => $volume,
                ],
                [
                    'metric' => 'overdueCount',
                    'value' => $overdueCount,
                ],
            ],
        ];
    }

    private function getAverageLeadTimeDays(): ?float
    {
        $averageSeconds = $this->connection->fetchOne(
            'SELECT AVG(TIMESTAMPDIFF(SECOND, shipped_at, delivered_at))
             FROM lieferzeiten_package
             WHERE shipped_at IS NOT NULL
               AND delivered_at IS NOT NULL'
        );

        if ($averageSeconds === null) {
            return null;
        }

        $averageSeconds = (float) $averageSeconds;

        if ($averageSeconds <= 0.0) {
            return null;
        }

        return round($averageSeconds / 86400, 1);
    }

    private function getVolume(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM lieferzeiten_package');
    }

    /**
     * @return array{0:int,1:int}
     */
    private function getOverdueStats(): array
    {
        $data = $this->connection->fetchAssociative(
            'SELECT
                SUM(CASE
                    WHEN COALESCE(delivered_at, shipped_at) > COALESCE(latest_delivery_at, latest_shipping_at)
                    THEN 1
                    ELSE 0
                END) AS overdue_count,
                COUNT(*) AS total_count
             FROM lieferzeiten_package
             WHERE COALESCE(latest_delivery_at, latest_shipping_at) IS NOT NULL
               AND COALESCE(delivered_at, shipped_at) IS NOT NULL'
        );

        $overdueCount = isset($data['overdue_count']) ? (int) $data['overdue_count'] : 0;
        $totalCount = isset($data['total_count']) ? (int) $data['total_count'] : 0;

        return [$overdueCount, $totalCount];
    }
}
