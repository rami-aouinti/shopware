<?php declare(strict_types=1);

namespace ExternalOrders\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

readonly class ExternalOrderService
{
    public function __construct(
        private EntityRepository $orderRepository,
        private Connection $connection,
    ) {
    }

    public function fetchOrders(
        Context $context,
        ?string $channel = null,
        ?string $search = null,
        int $page = 1,
        int $limit = 50,
        ?string $sort = null,
        ?string $order = null
    ): array {
        $page = max(1, $page);
        $limit = $limit > 0 ? $limit : 50;
        $sortField = $this->resolveSortField($sort);
        $sortDirection = $this->resolveSortDirection($order);

        $criteria = new Criteria();
        $criteria->setLimit(5000);
        $result = $this->orderRepository->search($criteria, $context);

        $orderIds = [];
        foreach ($result->getEntities() as $entity) {
            $orderIds[] = $entity->getId();
        }

        $metadataByOrderId = $this->fetchMetadataByOrderIds($orderIds);

        $orders = [];
        foreach ($result->getEntities() as $entity) {
            $metadata = $metadataByOrderId[$entity->getId()] ?? null;
            $externalId = $this->resolveExternalId($entity->getCustomFields(), $metadata);
            if ($externalId === null) {
                continue;
            }

            $orders[] = $this->mapOrderToListItem($entity, $externalId, $metadata);
        }

        if ($channel) {
            $orders = array_values(array_filter(
                $orders,
                static fn (array $orderItem): bool => ($orderItem['channel'] ?? '') === $channel
            ));
        }

        if ($search) {
            $orders = array_values(array_filter(
                $orders,
                static function (array $orderItem) use ($search): bool {
                    $haystacks = [
                        $orderItem['orderNumber'] ?? '',
                        $orderItem['customerName'] ?? '',
                        $orderItem['orderReference'] ?? '',
                        $orderItem['email'] ?? '',
                    ];

                    foreach ($haystacks as $value) {
                        if ($value !== '' && mb_stripos((string) $value, $search) !== false) {
                            return true;
                        }
                    }

                    return false;
                }
            ));
        }

        $orders = $this->sortOrders($orders, $sortField, $sortDirection);
        $total = count($orders);
        $orders = array_slice($orders, ($page - 1) * $limit, $limit);

        $totalRevenue = 0.0;
        $totalItems = 0;

        foreach ($orders as $orderItem) {
            $totalRevenue += (float) ($orderItem['totalRevenue'] ?? 0.0);
            $totalItems += (int) ($orderItem['totalItems'] ?? 0);
        }

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int) ceil($total / max(1, $limit)),
            'totalElements' => $total,
            'summary' => [
                'orderCount' => count($orders),
                'totalOrders' => count($orders),
                'totalRevenue' => $totalRevenue,
                'totalItems' => $totalItems,
                'totalQuantity' => $totalItems,
            ],
            'orders' => $orders,
        ];
    }

    public function fetchOrderDetail(Context $context, string $orderId): ?array
    {
        $criteria = new Criteria([$orderId]);

        $entity = $this->orderRepository->search($criteria, $context)->first();
        if ($entity === null) {
            return null;
        }

        $metadataByOrderId = $this->fetchMetadataByOrderIds([$entity->getId()]);
        $metadata = $metadataByOrderId[$entity->getId()] ?? null;
        $externalId = $this->resolveExternalId($entity->getCustomFields(), $metadata);
        if ($externalId === null) {
            return null;
        }

        return $this->mapOrderToDetail($entity, $externalId, $metadata);
    }

    /**
     * @param array<int, string> $orderIds
     *
     * @return array{updated:int, alreadyMarked:int, notFound:int}
     */
    public function markOrdersAsTest(Context $context, array $orderIds): array
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string) $id),
            $orderIds,
        ), static fn (string $id): bool => $id !== '')));

        if ($normalizedIds === []) {
            return ['updated' => 0, 'alreadyMarked' => 0, 'notFound' => 0];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $normalizedIds));
        $entities = $this->orderRepository->search($criteria, $context)->getEntities();

        $metadataByOrderId = $this->fetchMetadataByOrderIds($normalizedIds);

        $upserts = [];
        $foundIds = [];
        $alreadyMarked = 0;

        foreach ($entities as $entity) {
            $metadata = $metadataByOrderId[$entity->getId()] ?? null;
            $externalId = $this->resolveExternalId($entity->getCustomFields(), $metadata);
            if ($externalId === null) {
                continue;
            }

            $customFields = $entity->getCustomFields() ?? [];

            $foundIds[] = $entity->getId();
            $isAlreadyMarked = (bool) ($customFields['external_order_is_test_order'] ?? false);
            if ($isAlreadyMarked) {
                $alreadyMarked++;
                continue;
            }

            $customFields['external_order_is_test_order'] = true;
            $customFields['external_order_status'] = 'test';
            $customFields['external_order_status_label'] = 'Test';

            $upserts[] = [
                'id' => $entity->getId(),
                'customFields' => $customFields,
            ];

            if ($metadata !== null) {
                $payload = $metadata['rawPayload'];
                $payload['isTestOrder'] = true;
                $payload['status'] = 'test';
                $payload['statusLabel'] = 'Test';
                $payload['ordersStatusName'] = 'Test';
                $payload['orderStatusColor'] = '9e9e9e';

                if (isset($payload['detail']) && is_array($payload['detail'])) {
                    $payload['detail']['additional']['status'] = 'Test';
                }

                $this->connection->update('external_order_data', [
                    'raw_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'source_status' => 'test',
                    'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
                ], ['id' => hex2bin($metadata['id'])]);
            }
        }

        if ($upserts !== []) {
            $this->orderRepository->upsert($upserts, $context);
        }

        return [
            'updated' => count($upserts),
            'alreadyMarked' => $alreadyMarked,
            'notFound' => count(array_diff($normalizedIds, $foundIds)),
        ];
    }

    private function mapOrderToListItem(OrderEntity $order, string $externalId, ?array $metadata): array
    {
        $payload = $metadata['rawPayload'] ?? [];
        $detail = $payload['detail'] ?? null;
        $detail = is_array($detail) ? $detail : null;

        $customer = $detail['customer'] ?? null;
        $customer = is_array($customer) ? $customer : [];
        $additional = $detail['additional'] ?? null;
        $additional = is_array($additional) ? $additional : [];
        $totals = $detail['totals'] ?? null;
        $totals = is_array($totals) ? $totals : [];
        $customerName = $payload['customersName']
            ?? $payload['customerName']
            ?? trim(($customer['firstName'] ?? '') . ' ' . ($customer['lastName'] ?? ''));
        $customerName = $customerName !== '' ? $customerName : 'N/A';

        $email = $payload['customersEmailAddress'] ?? $payload['email'] ?? ($customer['email'] ?? 'N/A');
        $orderNumber = $payload['orderNumber'] ?? ($detail['orderNumber'] ?? $order->getOrderNumber() ?? $externalId);
        $orderReference = $payload['auftragNumber'] ?? $payload['orderReference'] ?? $orderNumber;
        $channel = $payload['channel'] ?? 'unknown';

        $statusLabel = $payload['ordersStatusName'] ?? $payload['statusLabel'] ?? ($additional['status'] ?? 'Processing');
        $status = $payload['status'] ?? strtolower((string) $statusLabel);

        $date = $payload['datePurchased'] ?? $payload['date'] ?? ($additional['orderDate'] ?? ($order->getOrderDateTime()?->format(DATE_ATOM) ?? ''));

        $totalItems = $payload['totalItems'] ?? $this->countDetailItems($detail);
        $totalRevenue = $payload['totalRevenue'] ?? ($totals['sum'] ?? $order->getAmountTotal() ?? 0.0);
        $orderId = $order->getId();
        $statusColor = $payload['orderStatusColor'] ?? ($additional['statusColor'] ?? null);
        $customFields = $order->getCustomFields() ?? [];
        $isTestOrder = (bool) (($customFields['external_order_is_test_order'] ?? null) ?? ($payload['isTestOrder'] ?? false));

        return [
            'id' => $order->getId(),
            'externalId' => $externalId,
            'orderId' => $orderId,
            'channel' => $channel,
            'orderNumber' => $orderNumber,
            'auftragNumber' => $orderReference,
            'customerName' => $customerName,
            'customersName' => $customerName,
            'orderReference' => $orderReference,
            'email' => $email,
            'customersEmailAddress' => $email,
            'date' => $date,
            'datePurchased' => $date,
            'status' => $status,
            'statusLabel' => $statusLabel,
            'ordersStatusName' => $statusLabel,
            'orderStatusColor' => $statusColor,
            'isTestOrder' => $isTestOrder,
            'totalItems' => (int) $totalItems,
            'totalRevenue' => (float) $totalRevenue,
        ];
    }

    private function mapOrderToDetail(OrderEntity $order, string $externalId, ?array $metadata): array
    {
        $payload = $metadata['rawPayload'] ?? [];
        if (isset($payload['detail']) && is_array($payload['detail'])) {
            $detail = $payload['detail'];
            $detail['items'] = $this->normalizeDetailItems($detail['items'] ?? []);
            $detail['internalOrderId'] = $order->getId();
            $detail['externalId'] = $externalId;

            return $detail;
        }

        return $this->buildDetailFallback($payload, $externalId);
    }

    /**
     * @param array<int, string> $orderIds
     *
     * @return array<string, array{id:string, externalId:string, channel:?string, rawPayload:array<string,mixed>}>
     */
    private function fetchMetadataByOrderIds(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter($orderIds)));
        if ($orderIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) as id, LOWER(HEX(order_id)) as order_id, external_id, channel, raw_payload
             FROM external_order_data
             WHERE order_id IN (:orderIds)',
            ['orderIds' => array_map(static fn (string $id): string => hex2bin($id) ?: '', $orderIds)],
            ['orderIds' => ArrayParameterType::BINARY]
        );

        $result = [];
        foreach ($rows as $row) {
            $orderId = $row['order_id'] ?? null;
            $externalId = $row['external_id'] ?? null;
            if (!is_string($orderId) || !is_string($externalId) || $externalId === '') {
                continue;
            }

            $rawPayload = json_decode((string) ($row['raw_payload'] ?? '{}'), true);
            $result[$orderId] = [
                'id' => (string) ($row['id'] ?? ''),
                'externalId' => $externalId,
                'channel' => isset($row['channel']) ? (string) $row['channel'] : null,
                'rawPayload' => is_array($rawPayload) ? $rawPayload : [],
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed>|null $customFields
     * @param array{id:string, externalId:string, channel:?string, rawPayload:array<string,mixed>}|null $metadata
     */
    private function resolveExternalId(?array $customFields, ?array $metadata): ?string
    {
        $fromCustomFields = $customFields['external_order_id'] ?? null;
        if (is_string($fromCustomFields) && $fromCustomFields !== '') {
            return $fromCustomFields;
        }

        return $metadata['externalId'] ?? null;
    }

    private function buildDetailFallback(array $payload, string $externalId): array
    {
        $customerName = $payload['customerName'] ?? 'N/A';
        $names = array_values(array_filter(explode(' ', (string) $customerName), static fn (string $value) => $value !== ''));
        $firstName = $names[0] ?? 'N/A';
        $lastName = $names[1] ?? '';
        $orderNumber = $payload['orderNumber'] ?? $externalId;

        return [
            'orderNumber' => $orderNumber,
            'customer' => [
                'number' => 'N/A',
                'firstName' => $firstName,
                'lastName' => $lastName !== '' ? $lastName : 'N/A',
                'email' => $payload['email'] ?? 'N/A',
                'group' => 'N/A',
            ],
            'billingAddress' => [
                'company' => 'N/A',
                'street' => 'N/A',
                'zip' => 'N/A',
                'city' => 'N/A',
                'country' => 'N/A',
            ],
            'shippingAddress' => [
                'name' => $customerName,
                'street' => 'N/A',
                'zipCity' => 'N/A',
                'country' => 'N/A',
            ],
            'payment' => [
                'method' => 'N/A',
                'code' => 'N/A',
                'dueDate' => 'N/A',
                'outstanding' => 'N/A',
                'settled' => 'N/A',
                'extra' => 'N/A',
            ],
            'shipping' => [
                'method' => 'N/A',
                'carrier' => 'N/A',
                'trackingNumbers' => [],
            ],
            'additional' => [
                'orderDate' => $payload['date'] ?? '',
                'status' => $payload['statusLabel'] ?? 'Processing',
                'orderType' => 'N/A',
                'notes' => 'N/A',
                'consultant' => 'N/A',
                'tenant' => 'N/A',
                'san6OrderNumber' => $orderNumber,
                'orgaEntries' => [],
                'documents' => [],
                'pdmsId' => 'N/A',
                'pdmsVariant' => 'N/A',
                'topmArticleNumber' => 'N/A',
                'topmExecution' => 'N/A',
                'statusHistorySource' => 'Database',
            ],
            'items' => $this->normalizeDetailItems([]),
            'statusHistory' => [
                [
                    'status' => $payload['statusLabel'] ?? 'Processing',
                    'date' => $payload['date'] ?? '',
                    'comment' => '',
                ],
            ],
            'totals' => [
                'items' => 0.0,
                'shipping' => 0.0,
                'sum' => (float) ($payload['totalRevenue'] ?? 0.0),
                'tax' => 0.0,
                'net' => 0.0,
            ],
        ];
    }


    /**
     * @param mixed $items
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDetailItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalizedItems = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $orderedQuantity = (int) ($item['orderedQuantity'] ?? $item['quantity'] ?? 0);
            $shippedQuantity = (int) ($item['shippedQuantity'] ?? $orderedQuantity);

            $item['quantity'] = $orderedQuantity;
            $item['orderedQuantity'] = $orderedQuantity;
            $item['shippedQuantity'] = $shippedQuantity;

            $normalizedItems[] = $item;
        }

        return $normalizedItems;
    }

    private function countDetailItems(?array $detail): int
    {
        if (!is_array($detail) || !isset($detail['items']) || !is_array($detail['items'])) {
            return 0;
        }

        $total = 0;
        foreach ($detail['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $total += (int) ($item['quantity'] ?? 0);
        }

        return $total;
    }

    private function resolveSortField(?string $sort): string
    {
        $allowed = [
            'orderNumber' => 'orderNumber',
            'orderReference' => 'orderReference',
            'customerName' => 'customerName',
            'email' => 'email',
            'date' => 'date',
            'statusLabel' => 'statusLabel',
        ];

        if ($sort !== null && $sort !== '') {
            return $allowed[$sort] ?? $allowed['date'];
        }

        return $allowed['date'];
    }

    private function resolveSortDirection(?string $order): string
    {
        return strtoupper((string) $order) === FieldSorting::ASCENDING
            ? FieldSorting::ASCENDING
            : FieldSorting::DESCENDING;
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     *
     * @return array<int, array<string, mixed>>
     */
    private function sortOrders(array $orders, string $sortField, string $sortDirection): array
    {
        usort($orders, static function (array $left, array $right) use ($sortField, $sortDirection): int {
            $leftValue = $left[$sortField] ?? '';
            $rightValue = $right[$sortField] ?? '';

            if ($sortField === 'date') {
                $leftValue = strtotime((string) $leftValue) ?: 0;
                $rightValue = strtotime((string) $rightValue) ?: 0;
            }

            $comparison = $leftValue <=> $rightValue;

            return $sortDirection === FieldSorting::ASCENDING ? $comparison : -$comparison;
        });

        return $orders;
    }
}
