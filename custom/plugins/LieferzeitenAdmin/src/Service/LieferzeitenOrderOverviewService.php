<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Doctrine\DBAL\Connection;

readonly class LieferzeitenOrderOverviewService
{
    private const FILTERABLE_FIELDS = [
        'bestellnummer',
        'san6',
        'orderDateFrom',
        'orderDateTo',
        'shippingDateFrom',
        'shippingDateTo',
        'deliveryDateFrom',
        'deliveryDateTo',
        'user',
        'sendenummer',
        'status',
        'shippingAssignmentType',
        'businessDateFrom',
        'businessDateTo',
    ];

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param array<string, scalar|null> $filters
     * @return array<string, mixed>
     */
    public function listOrders(
        ?int $page = 1,
        ?int $limit = 25,
        ?string $sort = null,
        ?string $order = null,
        array $filters = [],
    ): array {
        $page = max(1, (int) $page);
        $limit = max(1, min(200, (int) $limit));
        $sortField = $this->resolveSortField($sort);
        $sortDirection = $this->resolveSortDirection($order);

        $filters = $this->sanitizeFilters($filters);

        $params = [];
        $joins = [];
        $where = $this->buildWhereSql($filters, $params, $joins);

        $joinSql = implode(' ', array_unique($joins));

        $totalSql = sprintf(
            'SELECT COUNT(DISTINCT p.id)
             FROM `lieferzeiten_paket` p
             %s
             %s',
            $joinSql,
            $where,
        );

        $total = (int) $this->connection->fetchOne($totalSql, $params);

        $dataSql = sprintf(
            'SELECT
                LOWER(HEX(p.id)) AS id,
                p.external_order_id AS bestellnummer,
                p.paket_number AS san6,
                p.order_date AS bestelldatum,
                p.shipping_date AS spaetester_versand,
                p.delivery_date AS spaeteste_lieferung,
                p.last_changed_by AS user,
                p.status AS status,
                p.shipping_assignment_type AS shipping_assignment_type,
                MAX(sh.sendenummer) AS sendenummer
             FROM `lieferzeiten_paket` p
             %s
             %s
             GROUP BY p.id, p.external_order_id, p.paket_number, p.order_date, p.shipping_date, p.delivery_date, p.last_changed_by, p.status, p.shipping_assignment_type
             ORDER BY %s %s
             LIMIT :limit OFFSET :offset',
            $joinSql,
            $where,
            $sortField,
            $sortDirection,
        );

        $dataParams = $params;
        $dataParams['limit'] = $limit;
        $dataParams['offset'] = ($page - 1) * $limit;

        $rows = $this->connection->fetchAllAssociative($dataSql, $dataParams);

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'filterableFields' => self::FILTERABLE_FIELDS,
            'nonFilterableFields' => ['san6Pos', 'comment'],
            'sortingFields' => [
                'bestelldatum',
                'spaetesterVersand',
                'spaetesteLieferung',
            ],
            'data' => $rows,
        ];
    }

    /**
     * @param array<string, scalar|null> $filters
     * @return array<string, scalar|null>
     */
    private function sanitizeFilters(array $filters): array
    {
        $sanitized = [];
        foreach (self::FILTERABLE_FIELDS as $field) {
            if (!array_key_exists($field, $filters)) {
                continue;
            }
            $value = $filters[$field];
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            $sanitized[$field] = $value;
        }

        return $sanitized;
    }

    /**
     * @param array<string, scalar|null> $filters
     * @param array<string, mixed> $params
     * @param array<int, string> $joins
     */
    private function buildWhereSql(array $filters, array &$params, array &$joins): string
    {
        $conditions = ['COALESCE(p.is_test_order, 0) = 0'];

        if (($value = trim((string) ($filters['bestellnummer'] ?? ''))) !== '') {
            $conditions[] = 'p.external_order_id LIKE :bestellnummer';
            $params['bestellnummer'] = $value . '%';
        }

        if (($value = trim((string) ($filters['san6'] ?? ''))) !== '') {
            $conditions[] = 'p.paket_number LIKE :san6';
            $params['san6'] = $value . '%';
        }

        if (($value = trim((string) ($filters['user'] ?? ''))) !== '') {
            $conditions[] = 'p.last_changed_by LIKE :user';
            $params['user'] = '%' . $value . '%';
        }

        if (($value = trim((string) ($filters['status'] ?? ''))) !== '') {
            $conditions[] = 'p.status LIKE :status';
            $params['status'] = '%' . $value . '%';
        }

        if (($value = trim((string) ($filters['shippingAssignmentType'] ?? ''))) !== '') {
            $conditions[] = 'p.shipping_assignment_type = :shippingAssignmentType';
            $params['shippingAssignmentType'] = $value;
        }

        if (($value = trim((string) ($filters['sendenummer'] ?? ''))) !== '') {
            $joins[] = 'LEFT JOIN `lieferzeiten_position` pos ON pos.paket_id = p.id';
            $joins[] = 'LEFT JOIN `lieferzeiten_sendenummer_history` sh ON sh.position_id = pos.id';
            $conditions[] = 'sh.sendenummer LIKE :sendenummer';
            $params['sendenummer'] = '%' . $value . '%';
        } else {
            $joins[] = 'LEFT JOIN `lieferzeiten_position` pos ON pos.paket_id = p.id';
            $joins[] = 'LEFT JOIN `lieferzeiten_sendenummer_history` sh ON sh.position_id = pos.id';
        }

        $this->addDateRangeCondition($conditions, $params, 'p.order_date', 'orderDateFrom', 'orderDateTo', $filters);
        $this->addDateRangeCondition($conditions, $params, 'p.shipping_date', 'shippingDateFrom', 'shippingDateTo', $filters);
        $this->addDateRangeCondition($conditions, $params, 'p.delivery_date', 'deliveryDateFrom', 'deliveryDateTo', $filters);
        $this->addDateRangeCondition($conditions, $params, 'p.business_date_from', 'businessDateFrom', 'businessDateTo', $filters);

        if ($conditions === []) {
            return '';
        }

        return 'WHERE ' . implode(' AND ', $conditions);
    }

    /**
     * @param array<int, string> $conditions
     * @param array<string, mixed> $params
     * @param array<string, scalar|null> $filters
     */
    private function addDateRangeCondition(
        array &$conditions,
        array &$params,
        string $column,
        string $fromKey,
        string $toKey,
        array $filters,
    ): void {
        $fromValue = trim((string) ($filters[$fromKey] ?? ''));
        if ($fromValue !== '') {
            $conditions[] = sprintf('%s >= :%s', $column, $fromKey);
            $params[$fromKey] = $fromValue . ' 00:00:00';
        }

        $toValue = trim((string) ($filters[$toKey] ?? ''));
        if ($toValue !== '') {
            $conditions[] = sprintf('%s <= :%s', $column, $toKey);
            $params[$toKey] = $toValue . ' 23:59:59';
        }
    }

    private function resolveSortField(?string $sort): string
    {
        $allowed = [
            'bestelldatum' => 'p.order_date',
            'orderDate' => 'p.order_date',
            'spaetesterVersand' => 'p.shipping_date',
            'latestShippingDate' => 'p.shipping_date',
            'spaetesteLieferung' => 'p.delivery_date',
            'latestDeliveryDate' => 'p.delivery_date',
        ];

        if ($sort !== null && $sort !== '') {
            return $allowed[$sort] ?? 'p.order_date';
        }

        return 'p.order_date';
    }

    private function resolveSortDirection(?string $order): string
    {
        return strtoupper((string) $order) === 'ASC' ? 'ASC' : 'DESC';
    }
}
