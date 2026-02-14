<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

readonly class LieferzeitenOrderOverviewService
{
    /**
     * @var array<int, string>
     */
    private const BUSINESS_STATUS_MAPPING = [
        1 => 'New',
        2 => 'In clarification',
        3 => 'Awaiting supplier',
        4 => 'Partially available',
        5 => 'Ready for shipping',
        6 => 'Partially shipped',
        7 => 'Shipped',
        8 => 'Closed',
    ];

    private const FILTERABLE_FIELDS = [
        'bestellnummer',
        'san6',
        'san6Pos',
        'orderDateFrom',
        'orderDateTo',
        'shippingDateFrom',
        'shippingDateTo',
        'deliveryDateFrom',
        'deliveryDateTo',
        'user',
        'sendenummer',
        'status',
        'positionStatus',
        'paymentMethod',
        'shippingAssignmentType',
        'businessDateFrom',
        'businessDateTo',
        'businessDateEndFrom',
        'businessDateEndTo',
        'paymentDateFrom',
        'paymentDateTo',
        'calculatedDeliveryDateFrom',
        'calculatedDeliveryDateTo',
        'lieferterminLieferantFrom',
        'lieferterminLieferantTo',
        'neuerLieferterminFrom',
        'neuerLieferterminTo',
        'domain',
        'rowMode',
    ];


    /**
     * @var array<string, list<string>>
     */
    private const DOMAIN_SOURCE_MAPPING = [
        'first-medical-e-commerce' => ['first medical', 'e-commerce', 'shopware', 'gambio'],
        'medical-solutions' => ['medical solutions', 'medical-solutions', 'medical_solutions'],
    ];

    /**
     * @var array<string, string>
     */
    private const DOMAIN_ALIASES = [
        'First Medical' => 'first-medical-e-commerce',
        'E-Commerce' => 'first-medical-e-commerce',
        'First Medical - E-Commerce' => 'first-medical-e-commerce',
        'Medical Solutions' => 'medical-solutions',
    ];

    private const LEGACY_DOMAIN_MAPPING = [
        'first medical' => 'first-medical-e-commerce',
        'e-commerce' => 'first-medical-e-commerce',
        'first medical - e-commerce' => 'first-medical-e-commerce',
        'medical solutions' => 'medical-solutions',
    ];

    private const SORTABLE_FIELDS = [
        'bestelldatum' => 'p.order_date',
        'orderDate' => 'p.order_date',
        'spaetesterVersand' => 'p.shipping_date',
        'latestShippingDate' => 'p.shipping_date',
        'spaetesteLieferung' => 'p.delivery_date',
        'latestDeliveryDate' => 'p.delivery_date',
        'businessDateFrom' => 'p.business_date_from',
        'businessDateTo' => 'p.business_date_to',
        'paymentDate' => 'p.payment_date',
        'calculatedDeliveryDate' => 'p.calculated_delivery_date',
        'status' => 'p.status',
        'shippingAssignmentType' => 'p.shipping_assignment_type',
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
        $orderBy = $this->buildOrderBySql($sortField, $sortDirection);

        $filters = $this->sanitizeFilters($filters);
        $linePerParcel = mb_strtolower(trim((string) ($filters['rowMode'] ?? ''))) === 'parcel';

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

        $paramTypes = isset($params['sourceSystems'])
            ? ['sourceSystems' => ArrayParameterType::STRING]
            : [];

        $total = (int) $this->connection->fetchOne($totalSql, $params, $paramTypes);

        $dataSql = sprintf(
            'SELECT
                LOWER(HEX(p.id)) AS id,
                p.external_order_id AS bestellnummer,
                p.external_order_id AS orderNumber,
                p.paket_number AS san6,
                p.paket_number AS san6OrderNumber,
                GROUP_CONCAT(DISTINCT pos.position_number ORDER BY pos.position_number SEPARATOR ", ") AS san6Pos,
                GROUP_CONCAT(DISTINCT pos.position_number ORDER BY pos.position_number SEPARATOR ", ") AS san6Position,
                COUNT(DISTINCT pos.id) AS positionsCount,
                p.partial_shipment_quantity AS quantity,
                p.order_date AS bestelldatum,
                p.order_date AS orderDate,
                p.shipping_date AS spaetester_versand,
                p.shipping_date AS latestShippingDate,
                p.delivery_date AS spaeteste_lieferung,
                p.delivery_date AS latestDeliveryDate,
                p.payment_method AS paymentMethod,
                p.payment_date AS paymentDate,
                p.business_date_from AS businessDateFrom,
                p.business_date_to AS businessDateTo,
                p.calculated_delivery_date AS calculatedDeliveryDate,
                p.last_changed_by AS user,
                p.last_changed_by AS changedBy,
                p.last_changed_at AS changedAt,
                p.status AS status,
                GROUP_CONCAT(DISTINCT pos.status ORDER BY pos.status SEPARATOR ", ") AS positionStatus,
                p.shipping_assignment_type AS shipping_assignment_type,
                p.shipping_assignment_type AS shippingAssignmentType,
                p.source_system AS sourceSystem,
                p.source_system AS domain,
                GROUP_CONCAT(DISTINCT sh.sendenummer ORDER BY sh.sendenummer SEPARATOR ", ") AS sendenummer,
                GROUP_CONCAT(DISTINCT sh.sendenummer ORDER BY sh.sendenummer SEPARATOR ", ") AS trackingSummary,
                MAX(llh.liefertermin_to) AS lieferterminLieferantTo,
                MIN(llh.liefertermin_from) AS lieferterminLieferantFrom,
                MAX(nlh.liefertermin_to) AS neuerLieferterminTo,
                MIN(nlh.liefertermin_from) AS neuerLieferterminFrom
             FROM `lieferzeiten_paket` p
             %s
             %s
             GROUP BY p.id, p.external_order_id, p.paket_number, p.partial_shipment_quantity, p.order_date, p.shipping_date, p.delivery_date, p.payment_method, p.payment_date, p.business_date_from, p.business_date_to, p.calculated_delivery_date, p.last_changed_by, p.last_changed_at, p.status, p.shipping_assignment_type, p.source_system
             ORDER BY %s
             LIMIT :limit OFFSET :offset',
            $joinSql,
            $where,
            $orderBy,
        );

        $dataParams = $params;
        $dataParams['limit'] = $limit;
        $dataParams['offset'] = ($page - 1) * $limit;

        $rows = $this->connection->fetchAllAssociative($dataSql, $dataParams, $paramTypes);
        $rows = array_map(fn (array $row): array => $this->appendBusinessStatus($row), $rows);

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'filterableFields' => self::FILTERABLE_FIELDS,
            'nonFilterableFields' => ['san6Pos', 'comment'],
            'rowModeApplied' => $linePerParcel ? 'parcel' : 'default',
            'sortingFields' => array_keys(self::SORTABLE_FIELDS),
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

        if (($value = trim((string) ($filters['paymentMethod'] ?? ''))) !== '') {
            $conditions[] = 'p.payment_method LIKE :paymentMethod';
            $params['paymentMethod'] = '%' . $value . '%';
        }

        if (($value = trim((string) ($filters['shippingAssignmentType'] ?? ''))) !== '') {
            $conditions[] = 'p.shipping_assignment_type = :shippingAssignmentType';
            $params['shippingAssignmentType'] = $value;
        }

        $domainSources = $this->resolveDomainSources(trim((string) ($filters['domain'] ?? '')));
        if ($domainSources !== []) {
            $conditions[] = 'p.source_system IN (:sourceSystems)';
            $params['sourceSystems'] = $domainSources;
        }

        $joins[] = 'LEFT JOIN `lieferzeiten_position` pos ON pos.paket_id = p.id';
        $joins[] = 'LEFT JOIN `lieferzeiten_sendenummer_history` sh ON sh.position_id = pos.id';
        $joins[] = 'LEFT JOIN `lieferzeiten_liefertermin_lieferant_history` llh ON llh.position_id = pos.id';
        $joins[] = 'LEFT JOIN `lieferzeiten_neuer_liefertermin_history` nlh ON nlh.position_id = pos.id';

        if (($value = trim((string) ($filters['sendenummer'] ?? ''))) !== '') {
            $conditions[] = 'sh.sendenummer LIKE :sendenummer';
            $params['sendenummer'] = '%' . $value . '%';
        }

        if (($value = trim((string) ($filters['san6Pos'] ?? ''))) !== '') {
            $conditions[] = 'pos.position_number LIKE :san6Pos';
            $params['san6Pos'] = '%' . $value . '%';
        }

        if (($value = trim((string) ($filters['positionStatus'] ?? ''))) !== '') {
            $conditions[] = 'pos.status LIKE :positionStatus';
            $params['positionStatus'] = '%' . $value . '%';
        }

        $this->addDateRangeCondition($conditions, $params, 'p.order_date', 'orderDateFrom', 'orderDateTo', $filters);
        $this->addDateRangeCondition($conditions, $params, 'p.shipping_date', 'shippingDateFrom', 'shippingDateTo', $filters);
        $this->addDateRangeCondition($conditions, $params, 'p.delivery_date', 'deliveryDateFrom', 'deliveryDateTo', $filters);
        $this->addDateRangeCondition($conditions, $params, 'p.business_date_from', 'businessDateFrom', 'businessDateTo', $filters);
        $this->addDateRangeCondition($conditions, $params, 'p.business_date_to', 'businessDateEndFrom', 'businessDateEndTo', $filters);
        $this->addDateRangeCondition($conditions, $params, 'p.payment_date', 'paymentDateFrom', 'paymentDateTo', $filters);
        $this->addDateRangeCondition($conditions, $params, 'p.calculated_delivery_date', 'calculatedDeliveryDateFrom', 'calculatedDeliveryDateTo', $filters);

        $this->addLatestHistoryRangeCondition(
            $conditions,
            $params,
            'liefertermin_lieferant_history',
            'lieferterminLieferantFrom',
            'lieferterminLieferantTo',
            $filters,
            'llh',
        );
        $this->addLatestHistoryRangeCondition(
            $conditions,
            $params,
            'neuer_liefertermin_paket_history',
            'neuerLieferterminFrom',
            'neuerLieferterminTo',
            $filters,
            'nlh',
        );


        $domainFilter = $this->resolveDomainFilter((string) ($filters['domain'] ?? ''));
        if ($domainFilter !== []) {
            $placeholders = [];
            foreach ($domainFilter as $index => $sourceSystem) {
                $paramName = sprintf('domainSource%d', $index);
                $params[$paramName] = $sourceSystem;
                $placeholders[] = ':' . $paramName;
            }

            $conditions[] = sprintf('LOWER(COALESCE(p.source_system, "")) IN (%s)', implode(', ', $placeholders));
        }

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

    /**
     * @param array<int, string> $conditions
     * @param array<string, mixed> $params
     * @param array<string, scalar|null> $filters
     */
    private function addLatestHistoryRangeCondition(
        array &$conditions,
        array &$params,
        string $historyTableSuffix,
        string $fromKey,
        string $toKey,
        array $filters,
        string $alias,
    ): void {
        $fromValue = trim((string) ($filters[$fromKey] ?? ''));
        $toValue = trim((string) ($filters[$toKey] ?? ''));
        if ($fromValue === '' && $toValue === '') {
            return;
        }

        $historyConditions = [];
        if ($fromValue !== '') {
            $historyConditions[] = sprintf('%s.liefertermin_to >= :%s', $alias, $fromKey);
            $params[$fromKey] = $fromValue . ' 00:00:00';
        }

        if ($toValue !== '') {
            $historyConditions[] = sprintf('%s.liefertermin_from <= :%s', $alias, $toKey);
            $params[$toKey] = $toValue . ' 23:59:59';
        }

        $conditions[] = sprintf(
            'EXISTS (
                SELECT 1
                FROM `lieferzeiten_position` pos_filter
                INNER JOIN `lieferzeiten_%1$s` %2$s ON %2$s.paket_id = p.id
                WHERE pos_filter.paket_id = p.id
                  AND %2$s.id = (
                      SELECT latest.id
                      FROM `lieferzeiten_%1$s` latest
                      WHERE latest.paket_id = p.id
                      ORDER BY latest.created_at DESC
                      LIMIT 1
                  )
                  AND %3$s
            )',
            $historyTableSuffix,
            $alias,
            implode(' AND ', $historyConditions),
        );
    }

    /**
     * @return list<string>
     */
    private function resolveDomainSources(string $domain): array
    {
        if ($domain === '') {
            return [];
        }

        $domainKey = self::DOMAIN_ALIASES[$domain] ?? strtolower($domain);

        return self::DOMAIN_SOURCE_MAPPING[$domainKey] ?? [$domain];
    }

    private function resolveSortField(?string $sort): string
    {
        if ($sort !== null && $sort !== '') {
            return self::SORTABLE_FIELDS[$sort] ?? 'p.order_date';
        }

        return 'p.order_date';
    }

    private function buildOrderBySql(string $sortField, string $sortDirection): string
    {
        $parts = [sprintf('%s %s', $sortField, $sortDirection)];
        if ($sortField !== 'p.order_date') {
            $parts[] = 'p.order_date DESC';
        }

        $parts[] = 'p.id DESC';

        return implode(', ', $parts);
    }

    private function resolveSortDirection(?string $order): string
    {
        return strtoupper((string) $order) === 'ASC' ? 'ASC' : 'DESC';
    }

    /**
     * @return array{code: int|null, labelKey: string|null}
     */
    private function buildBusinessStatusPayload(mixed $status): array
    {
        $statusCode = is_numeric($status) ? (int) $status : null;

        if ($statusCode === null || !isset(self::BUSINESS_STATUS_LABEL_KEYS[$statusCode])) {
            return [
                'code' => $statusCode,
                'labelKey' => null,
            ];
        }

        return [
            'code' => $statusCode,
            'labelKey' => self::BUSINESS_STATUS_LABEL_KEYS[$statusCode],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function appendBusinessStatus(array $row): array
    {
        $statusCode = is_numeric($row['status'] ?? null) ? (int) $row['status'] : null;
        $statusCodeString = $statusCode !== null ? (string) $statusCode : null;
        $statusLabel = $statusCode !== null ? (self::BUSINESS_STATUS_MAPPING[$statusCode] ?? 'Unknown') : 'Unknown';

        $row['businessStatus'] = [
            'code' => $statusCodeString,
            'label' => $statusLabel,
        ];
        $row['business_status'] = [
            'code' => $statusCodeString,
            'label' => $statusLabel,
        ];
        $row['business_status_label'] = $statusLabel;
        $row['last_changed_by'] = $row['last_changed_by'] ?? ($row['user'] ?? null);

        $row['positionsCount'] = (int) ($row['positionsCount'] ?? 0);

        if (($row['quantity'] ?? null) === null || trim((string) $row['quantity']) === '') {
            $row['quantity'] = (string) $row['positionsCount'];
        }

        return $row;
    }

}
