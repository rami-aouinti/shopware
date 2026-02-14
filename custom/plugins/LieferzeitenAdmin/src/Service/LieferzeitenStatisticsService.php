<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

readonly class LieferzeitenStatisticsService
{
    private const DOMAIN_SOURCE_MAPPING = [
        'first-medical-e-commerce' => ['first medical', 'e-commerce', 'shopware', 'gambio'],
        'medical-solutions' => ['medical solutions', 'medical-solutions', 'medical_solutions'],
    ];

    private const LEGACY_DOMAIN_MAPPING = [
        'first medical' => 'first-medical-e-commerce',
        'e-commerce' => 'first-medical-e-commerce',
        'first medical - e-commerce' => 'first-medical-e-commerce',
        'medical solutions' => 'medical-solutions',
    ];

    public function __construct(
        private Connection $connection,
        private ChannelDateSettingsProvider $channelDateSettingsProvider,
    )
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatistics(int $periodDays, ?string $domain, ?string $channel): array
    {
        $periodDays = $this->sanitizePeriod($periodDays);
        $periodStart = (new \DateTimeImmutable('now'))->setTime(0, 0)->modify(sprintf('-%d days', $periodDays - 1));
        $periodStartSql = $periodStart->format('Y-m-d H:i:s');
        $now = new \DateTimeImmutable('now');

        $params = [
            'periodStart' => $periodStartSql,
        ];

        $scopeSql = $this->buildScopeCondition($params, $domain, $channel);

        $metricsSql = sprintf(
            'SELECT
                SUM(CASE WHEN COALESCE(pos_stats.open_positions, 0) > 0 THEN 1 ELSE 0 END) AS open_orders,
                SUM(CASE WHEN COALESCE(pos_stats.open_positions, 0) > 0 AND p.shipping_date IS NOT NULL AND p.shipping_date < :now THEN 1 ELSE 0 END) AS overdue_shipping,
                SUM(CASE WHEN COALESCE(pos_stats.open_positions, 0) = 0 AND COALESCE(pos_stats.closed_positions, 0) > 0 AND p.delivery_date IS NOT NULL AND p.delivery_date < :now THEN 1 ELSE 0 END) AS overdue_delivery
            FROM `lieferzeiten_paket` p
            LEFT JOIN (
                SELECT
                    paket_id,
                    SUM(CASE WHEN LOWER(COALESCE(status, "")) IN ("done", "closed", "completed") THEN 0 ELSE 1 END) AS open_positions,
                    SUM(CASE WHEN LOWER(COALESCE(status, "")) IN ("done", "closed", "completed") THEN 1 ELSE 0 END) AS closed_positions
                FROM `lieferzeiten_position`
                GROUP BY paket_id
            ) pos_stats ON pos_stats.paket_id = p.id
            WHERE COALESCE(p.is_test_order, 0) = 0
              AND p.created_at >= :periodStart
              %s',
            $scopeSql,
        );

        $metricsParamTypes = isset($params['sourceSystems'])
            ? ['sourceSystems' => ArrayParameterType::STRING]
            : [];

        $metrics = $this->connection->fetchAssociative($metricsSql, $params, $metricsParamTypes) ?: [];
        $overdueCounts = $this->calculateOverdueCounts($periodStartSql, $domain, $channel, $now);

        $channelSql = sprintf(
            'SELECT
                COALESCE(NULLIF(p.source_system, ""), "Unknown") AS channel,
                COUNT(*) AS value
            FROM `lieferzeiten_paket` p
            WHERE COALESCE(p.is_test_order, 0) = 0
              AND p.created_at >= :periodStart
              %s
            GROUP BY COALESCE(NULLIF(p.source_system, ""), "Unknown")
            ORDER BY value DESC, channel ASC',
            $scopeSql,
        );

        $channels = $this->connection->fetchAllAssociative($channelSql, $params, $metricsParamTypes);

        $timelineSql = sprintf(
            'SELECT
                DATE(t.occurred_at) AS date,
                COUNT(*) AS count
            FROM (
                SELECT a.created_at AS occurred_at, a.source_system AS source_system
                FROM `lieferzeiten_audit_log` a
                WHERE a.created_at >= :periodStart

                UNION ALL

                SELECT t.created_at AS occurred_at, JSON_UNQUOTE(JSON_EXTRACT(t.payload, "$.sourceSystem")) AS source_system
                FROM `lieferzeiten_task` t
                WHERE t.created_at >= :periodStart

                UNION ALL

                SELECT pos.last_changed_at AS occurred_at, p.source_system AS source_system
                FROM `lieferzeiten_position` pos
                INNER JOIN `lieferzeiten_paket` p ON p.id = pos.paket_id
                WHERE pos.last_changed_at IS NOT NULL
                  AND pos.last_changed_at >= :periodStart
                  AND COALESCE(p.is_test_order, 0) = 0

                UNION ALL

                SELECT p.last_changed_at AS occurred_at, p.source_system AS source_system
                FROM `lieferzeiten_paket` p
                WHERE p.last_changed_at IS NOT NULL
                  AND p.last_changed_at >= :periodStart
                  AND COALESCE(p.is_test_order, 0) = 0
            ) t
            WHERE 1=1
              %s
            GROUP BY DATE(t.occurred_at)
            ORDER BY DATE(t.occurred_at) ASC',
            $this->buildSourceScopeCondition('t.source_system', $params, $domain, $channel),
        );

        $timeline = $this->connection->fetchAllAssociative($timelineSql, $params, $metricsParamTypes);

        $activitiesSql = sprintf(
            'SELECT
                LOWER(HEX(t.id)) AS id,
                t.order_number AS orderNumber,
                COALESCE(NULLIF(t.domain, ""), "Unknown") AS domain,
                t.event_type AS eventType,
                t.event_status AS status,
                t.message AS message,
                t.event_at AS eventAt,
                p.delivery_date AS promisedAt
            FROM (
                SELECT
                    a.id,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.payload, "$.externalOrderId")), p.external_order_id) AS order_number,
                    COALESCE(NULLIF(a.source_system, ""), p.source_system) AS domain,
                    "audit" AS event_type,
                    a.action AS event_status,
                    a.action AS message,
                    a.created_at AS event_at
                FROM `lieferzeiten_audit_log` a
                LEFT JOIN `lieferzeiten_paket` p ON p.external_order_id = JSON_UNQUOTE(JSON_EXTRACT(a.payload, "$.externalOrderId"))
                WHERE a.created_at >= :periodStart

                UNION ALL

                SELECT
                    t.id,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.payload, "$.externalOrderId")), p.external_order_id) AS order_number,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.payload, "$.sourceSystem")), p.source_system) AS domain,
                    "task" AS event_type,
                    t.status AS event_status,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.payload, "$.taskType")), "task") AS message,
                    t.created_at AS event_at
                FROM `lieferzeiten_task` t
                LEFT JOIN `lieferzeiten_paket` p ON p.external_order_id = JSON_UNQUOTE(JSON_EXTRACT(t.payload, "$.externalOrderId"))
                WHERE t.created_at >= :periodStart

                UNION ALL

                SELECT
                    pos.id,
                    p.external_order_id AS order_number,
                    p.source_system AS domain,
                    "position" AS event_type,
                    COALESCE(pos.status, "updated") AS event_status,
                    "position_updated" AS message,
                    pos.last_changed_at AS event_at
                FROM `lieferzeiten_position` pos
                INNER JOIN `lieferzeiten_paket` p ON p.id = pos.paket_id
                WHERE pos.last_changed_at IS NOT NULL
                  AND pos.last_changed_at >= :periodStart
                  AND COALESCE(p.is_test_order, 0) = 0

                UNION ALL

                SELECT
                    p.id,
                    p.external_order_id AS order_number,
                    p.source_system AS domain,
                    "paket" AS event_type,
                    COALESCE(p.status, "updated") AS event_status,
                    "paket_updated" AS message,
                    p.last_changed_at AS event_at
                FROM `lieferzeiten_paket` p
                WHERE p.last_changed_at IS NOT NULL
                  AND p.last_changed_at >= :periodStart
                  AND COALESCE(p.is_test_order, 0) = 0
            ) t
            LEFT JOIN `lieferzeiten_paket` p ON p.external_order_id = t.order_number
            WHERE t.order_number IS NOT NULL
              %s
            ORDER BY t.event_at DESC
            LIMIT 200',
            $this->buildSourceScopeCondition('t.domain', $params, $domain, $channel),
        );

        $activities = $this->connection->fetchAllAssociative($activitiesSql, $params, $metricsParamTypes);

        return [
            'periodDays' => $periodDays,
            'metrics' => [
                'openOrders' => (int) ($metrics['open_orders'] ?? 0),
                'overdueShipping' => (int) ($metrics['overdue_shipping'] ?? 0),
                'overdueDelivery' => (int) ($metrics['overdue_delivery'] ?? 0),
            ],
            'channels' => array_map(static fn (array $row): array => [
                'channel' => (string) ($row['channel'] ?? 'Unknown'),
                'value' => (int) ($row['value'] ?? 0),
            ], $channels),
            'timeline' => array_map(static fn (array $row): array => [
                'date' => (string) ($row['date'] ?? ''),
                'count' => (int) ($row['count'] ?? 0),
            ], $timeline),
            'activitiesData' => array_map(static fn (array $row): array => [
                'id' => (string) ($row['id'] ?? ''),
                'orderNumber' => (string) ($row['orderNumber'] ?? ''),
                'domain' => (string) ($row['domain'] ?? 'Unknown'),
                'status' => (string) ($row['status'] ?? ''),
                'eventType' => (string) ($row['eventType'] ?? ''),
                'message' => (string) ($row['message'] ?? ''),
                'eventAt' => (string) ($row['eventAt'] ?? ''),
                'promisedAt' => $row['promisedAt'],
            ], $activities),
        ];
    }

    /**
     * @return array{shipping:int,delivery:int}
     */
    private function calculateOverdueCounts(string $periodStartSql, ?string $domain, ?string $channel, \DateTimeImmutable $now): array
    {
        $params = [
            'periodStart' => $periodStartSql,
        ];
        $scopeSql = $this->buildScopeCondition($params, $domain, $channel);

        $rows = $this->connection->fetchAllAssociative(sprintf(
            'SELECT
                p.source_system,
                p.shipping_date,
                p.delivery_date,
                COALESCE(pos_stats.open_positions, 0) AS open_positions
            FROM `lieferzeiten_paket` p
            LEFT JOIN (
                SELECT paket_id, SUM(CASE WHEN LOWER(COALESCE(status, "")) IN ("done", "closed", "completed") THEN 0 ELSE 1 END) AS open_positions
                FROM `lieferzeiten_position`
                GROUP BY paket_id
            ) pos_stats ON pos_stats.paket_id = p.id
            WHERE COALESCE(p.is_test_order, 0) = 0
              AND p.created_at >= :periodStart
              %s',
            $scopeSql,
        ), $params);

        $shippingOverdue = 0;
        $deliveryOverdue = 0;

        foreach ($rows as $row) {
            if ((int) ($row['open_positions'] ?? 0) <= 0) {
                continue;
            }

            $sourceSystem = (string) ($row['source_system'] ?? 'shopware');
            $settings = $this->channelDateSettingsProvider->getForChannel($sourceSystem);

            $shippingDate = $this->parseDateValue($row['shipping_date'] ?? null);
            if ($shippingDate !== null && $shippingDate < $this->buildThresholdDate($now, $settings['shipping'])) {
                ++$shippingOverdue;
            }

            $deliveryDate = $this->parseDateValue($row['delivery_date'] ?? null);
            if ($deliveryDate !== null && $deliveryDate < $this->buildThresholdDate($now, $settings['delivery'])) {
                ++$deliveryOverdue;
            }
        }

        return ['shipping' => $shippingOverdue, 'delivery' => $deliveryOverdue];
    }

    /**
     * @param array{workingDays:int,cutoff:string} $settings
     */
    private function buildThresholdDate(\DateTimeImmutable $now, array $settings): \DateTimeImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $settings['cutoff']));
        $threshold = $now->setTime($hour, $minute);

        $remaining = max(0, (int) ($settings['workingDays'] ?? 0));
        while ($remaining > 0) {
            $threshold = $threshold->modify('-1 day');
            $weekday = (int) $threshold->format('N');
            if ($weekday >= 6) {
                continue;
            }

            --$remaining;
        }

        return $threshold;
    }

    private function parseDateValue(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildScopeCondition(array &$params, ?string $domain, ?string $channel): string
    {
        $filter = $this->resolveSourceFilter($domain, $channel);
        if ($filter === []) {
            return '';
        }

        $placeholders = [];
        foreach ($filter as $index => $sourceSystem) {
            $paramName = sprintf('sourceSystem%d', $index);
            $params[$paramName] = $sourceSystem;
            $placeholders[] = ':' . $paramName;
        }

        return sprintf(' AND p.source_system IN (%s)', implode(', ', $placeholders));
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildSourceScopeCondition(string $column, array &$params, ?string $domain, ?string $channel): string
    {
        $filter = $this->resolveSourceFilter($domain, $channel);
        if ($filter === []) {
            return '';
        }

        $placeholders = [];
        foreach ($filter as $index => $sourceSystem) {
            $paramName = sprintf('sourceSystem%d', $index);
            $params[$paramName] = $sourceSystem;
            $placeholders[] = ':' . $paramName;
        }

        return sprintf(' AND %s IN (%s)', $column, implode(', ', $placeholders));
    }

    private function sanitizePeriod(int $periodDays): int
    {
        if (in_array($periodDays, [7, 30, 90], true)) {
            return $periodDays;
        }

        return 30;
    }

    /**
     * @return array<int, string>
     */
    public function resolveSourceFilter(?string $domain, ?string $channel): array
    {
        $channel = $this->normalizeSource((string) $channel);
        $domainKey = $this->normalizeDomainKey((string) $domain);

        if ($channel !== null && $channel !== 'all') {
            return [$channel];
        }

        if ($domainKey !== null) {
            return self::DOMAIN_SOURCE_MAPPING[$domainKey] ?? [$domainKey];
        }

        return [];
    }

    private function normalizeDomainKey(string $domain): ?string
    {
        $domain = $this->normalizeSource($domain);
        if ($domain === null) {
            return null;
        }

        return self::LEGACY_DOMAIN_MAPPING[$domain] ?? $domain;
    }

    private function normalizeSource(string $value): ?string
    {
        $value = trim(mb_strtolower($value));

        return $value !== '' ? $value : null;
    }
}
