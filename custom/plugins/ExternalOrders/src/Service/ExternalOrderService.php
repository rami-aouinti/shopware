<?php declare(strict_types=1);

namespace ExternalOrders\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

readonly class ExternalOrderService
{
    public function __construct(
        private EntityRepository $externalOrderRepository,
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
        $result = $this->externalOrderRepository->search($criteria, $context);

        $orders = [];
        foreach ($result->getEntities() as $entity) {
            $payload = $entity->getPayload();
            if (!is_array($payload)) {
                continue;
            }

            $orders[] = $this->mapExternalPayloadToListItem($payload, $entity->getExternalId());
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
            'summary' => [
                'orderCount' => count($orders),
                'totalRevenue' => $totalRevenue,
                'totalItems' => $totalItems,
            ],
            'orders' => $orders,
        ];
    }

    public function fetchOrderDetail(Context $context, string $orderId): ?array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('externalId', $orderId));

        $entity = $this->externalOrderRepository->search($criteria, $context)->first();
        if ($entity === null) {
            return null;
        }

        $payload = $entity->getPayload();
        if (!is_array($payload)) {
            return null;
        }

        return $this->mapExternalPayloadToDetail($payload, $entity->getExternalId());
    }

    private function mapExternalPayloadToListItem(array $payload, string $externalId): array
    {
        $detail = $payload['detail'] ?? null;
        $detail = is_array($detail) ? $detail : null;

        $customer = $detail['customer'] ?? null;
        $customer = is_array($customer) ? $customer : [];
        $additional = $detail['additional'] ?? null;
        $additional = is_array($additional) ? $additional : [];
        $totals = $detail['totals'] ?? null;
        $totals = is_array($totals) ? $totals : [];
        $customerName = $payload['customerName']
            ?? trim(($customer['firstName'] ?? '') . ' ' . ($customer['lastName'] ?? ''));
        $customerName = $customerName !== '' ? $customerName : 'N/A';

        $email = $payload['email'] ?? ($customer['email'] ?? 'N/A');
        $orderNumber = $payload['orderNumber'] ?? ($detail['orderNumber'] ?? $externalId);
        $orderReference = $payload['orderReference'] ?? $orderNumber;
        $channel = $payload['channel'] ?? 'unknown';

        $statusLabel = $payload['statusLabel'] ?? ($additional['status'] ?? 'Processing');
        $status = $payload['status'] ?? strtolower((string) $statusLabel);

        $date = $payload['date'] ?? ($additional['orderDate'] ?? '');

        $totalItems = $payload['totalItems'] ?? $this->countDetailItems($detail);
        $totalRevenue = $payload['totalRevenue'] ?? ($totals['sum'] ?? 0.0);

        return [
            'id' => $externalId,
            'channel' => $channel,
            'orderNumber' => $orderNumber,
            'customerName' => $customerName,
            'orderReference' => $orderReference,
            'email' => $email,
            'date' => $date,
            'status' => $status,
            'statusLabel' => $statusLabel,
            'totalItems' => (int) $totalItems,
            'totalRevenue' => (float) $totalRevenue,
        ];
    }

    private function mapExternalPayloadToDetail(array $payload, string $externalId): array
    {
        if (isset($payload['detail']) && is_array($payload['detail'])) {
            return $payload['detail'];
        }

        return $this->buildDetailFallback($payload, $externalId);
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
            'items' => [],
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
