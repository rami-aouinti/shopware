<?php declare(strict_types=1);

namespace ExternalOrders\Service;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

readonly class ExternalOrderService
{
    public function __construct(
        private EntityRepository $orderRepository,
        private bool $useDemoData = true
    )
    {
    }

    public function fetchOrders(
        Context $context,
        ?string $channel = null,
        ?string $search = null,
        int $page = 1,
        int $limit = 50,
        ?string $sort = null,
        ?string $order = null
    ): array
    {
        $page = max(1, $page);
        $limit = $limit > 0 ? $limit : 50;
        $sortField = $this->resolveSortField($sort);
        $sortDirection = $this->resolveSortDirection($order);

        if ($this->useDemoData) {
            $fakePayload = $this->buildFakeOrderPayload($channel, $search, $page, $limit, $sortField, $sortDirection);
            if ($fakePayload !== null) {
                return $fakePayload;
            }
        }

        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->addSorting(new FieldSorting($sortField, $sortDirection));
        $criteria->addAssociations([
            'orderCustomer',
            'billingAddress',
            'deliveries.shippingOrderAddress',
            'deliveries.shippingMethod',
            'transactions.paymentMethod',
            'lineItems',
            'stateMachineState',
        ]);

        if ($search !== null && $search !== '') {
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new ContainsFilter('orderNumber', $search),
                new ContainsFilter('orderCustomer.firstName', $search),
                new ContainsFilter('orderCustomer.lastName', $search),
                new ContainsFilter('orderCustomer.email', $search),
            ]));
        }

        $orders = [];
        $totalRevenue = 0.0;
        $totalItems = 0;

        $result = $this->orderRepository->search($criteria, $context);

        foreach ($result->getEntities() as $order) {
            $orders[] = $this->mapOrderListItem($order);
            $totalRevenue += (float) $order->getAmountTotal();
            $totalItems += $this->countItems($order);
        }

        return [
            'total' => $result->getTotal(),
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
        if ($this->useDemoData) {
            $fakeDetail = $this->buildFakeOrderDetail($orderId);
            if ($fakeDetail !== null) {
                return $fakeDetail;
            }
        }

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociations([
            'orderCustomer',
            'billingAddress',
            'deliveries.shippingOrderAddress',
            'deliveries.shippingMethod',
            'transactions.paymentMethod',
            'lineItems',
            'stateMachineState',
        ]);

        $order = $this->orderRepository->search($criteria, $context)->first();

        return $order ? $this->mapOrderDetail($order) : null;
    }

    private function mapOrderListItem(OrderEntity $order): array
    {
        $orderCustomer = $order->getOrderCustomer();
        $customerName = $orderCustomer
            ? trim($orderCustomer->getFirstName() . ' ' . $orderCustomer->getLastName())
            : 'N/A';
        $email = $orderCustomer?->getEmail() ?? 'N/A';
        $state = $order->getStateMachineState();

        return [
            'id' => $order->getId(),
            'channel' => $order->getSalesChannelId() ?? 'unknown',
            'orderNumber' => $order->getOrderNumber(),
            'customerName' => $customerName,
            'orderReference' => $order->getOrderNumber(),
            'email' => $email,
            'date' => $order->getOrderDateTime()?->format('Y-m-d H:i') ?? '',
            'status' => $state?->getTechnicalName() ?? 'processing',
            'statusLabel' => $state?->getName() ?? 'Processing',
            'totalItems' => $this->countItems($order),
        ];
    }

    private function mapOrderDetail(OrderEntity $order): array
    {
        $orderCustomer = $order->getOrderCustomer();
        $billingAddress = $order->getBillingAddress();
        $delivery = $order->getDeliveries()?->first();
        $shippingAddress = $delivery?->getShippingOrderAddress();
        $paymentMethod = $order->getTransactions()?->last()?->getPaymentMethod();
        $shippingMethod = $delivery?->getShippingMethod();
        $state = $order->getStateMachineState();
        $orderDate = $order->getOrderDateTime()?->format('Y-m-d H:i') ?? '';

        $items = [];
        foreach ($order->getLineItems() ?? [] as $lineItem) {
            $price = $lineItem->getPrice();
            $taxRate = 0.0;
            $taxAmount = 0.0;
            if ($price && $price->getCalculatedTaxes()->count() > 0) {
                $taxRate = $price->getCalculatedTaxes()->first()->getTaxRate();
                foreach ($price->getCalculatedTaxes() as $calculatedTax) {
                    $taxAmount += $calculatedTax->getTax();
                }
            }
            $quantity = max(1, $lineItem->getQuantity());
            $grossTotal = $price?->getTotalPrice() ?? 0.0;
            $grossUnitPrice = $price?->getUnitPrice() ?? ($grossTotal / $quantity);
            $netUnitPrice = $grossUnitPrice - ($taxAmount / $quantity);

            $items[] = [
                'name' => $lineItem->getLabel() ?? $lineItem->getId(),
                'quantity' => $lineItem->getQuantity(),
                'netPrice' => $netUnitPrice,
                'taxRate' => $taxRate,
                'grossPrice' => $grossTotal,
                'totalPrice' => $grossTotal,
            ];
        }

        $statusHistory = [
            [
                'status' => $state?->getName() ?? 'Processing',
                'date' => $orderDate,
                'comment' => $order->getCustomerComment() ?? '',
            ],
        ];

        $amountTotal = (float) $order->getAmountTotal();
        $amountNet = (float) $order->getAmountNet();
        $shippingTotal = (float) $order->getShippingTotal();
        $taxTotal = $amountTotal - $amountNet;

        $customerGroupName = 'N/A';
        if ($orderCustomer !== null && method_exists($orderCustomer, 'getCustomerGroup')) {
            $customerGroupName = $orderCustomer->getCustomerGroup()?->getName() ?? 'N/A';
        }

        return [
            'orderNumber' => $order->getOrderNumber(),
            'customer' => [
                'number' => $orderCustomer?->getCustomerNumber() ?? 'N/A',
                'firstName' => $orderCustomer?->getFirstName() ?? 'N/A',
                'lastName' => $orderCustomer?->getLastName() ?? 'N/A',
                'email' => $orderCustomer?->getEmail() ?? 'N/A',
                'group' => $customerGroupName,
            ],
            'billingAddress' => [
                'company' => $billingAddress?->getCompany() ?? 'N/A',
                'street' => $billingAddress?->getStreet() ?? 'N/A',
                'zip' => $billingAddress?->getZipcode() ?? 'N/A',
                'city' => $billingAddress?->getCity() ?? 'N/A',
                'country' => $billingAddress?->getCountry()?->getName() ?? 'N/A',
            ],
            'shippingAddress' => [
                'name' => $shippingAddress?->getFirstName() && $shippingAddress?->getLastName()
                    ? trim($shippingAddress->getFirstName() . ' ' . $shippingAddress->getLastName())
                    : ($shippingAddress?->getCompany() ?? 'N/A'),
                'street' => $shippingAddress?->getStreet() ?? 'N/A',
                'zipCity' => $shippingAddress
                    ? trim(($shippingAddress->getZipcode() ?? '') . ' ' . ($shippingAddress->getCity() ?? ''))
                    : 'N/A',
                'country' => $shippingAddress?->getCountry()?->getName() ?? 'N/A',
            ],
            'payment' => [
                'method' => $paymentMethod?->getName() ?? 'N/A',
                'code' => $paymentMethod?->getTechnicalName() ?? 'N/A',
                'dueDate' => 'N/A',
                'outstanding' => 'N/A',
                'settled' => 'N/A',
                'extra' => 'N/A',
            ],
            'shipping' => [
                'method' => $shippingMethod?->getName() ?? 'N/A',
                'carrier' => $shippingMethod?->getName() ?? 'N/A',
                'trackingNumbers' => $delivery?->getTrackingCodes() ?? [],
            ],
            'additional' => [
                'orderDate' => $orderDate,
                'status' => $state?->getName() ?? 'Processing',
                'orderType' => 'N/A',
                'notes' => $order->getCustomerComment() ?? 'N/A',
                'consultant' => 'N/A',
                'tenant' => 'N/A',
                'san6OrderNumber' => $order->getOrderNumber(),
                'orgaEntries' => [],
                'documents' => [],
                'pdmsId' => 'N/A',
                'pdmsVariant' => 'N/A',
                'topmArticleNumber' => 'N/A',
                'topmExecution' => 'N/A',
                'statusHistorySource' => 'Shopware',
            ],
            'items' => $items,
            'statusHistory' => $statusHistory,
            'totals' => [
                'items' => (float) $order->getPositionPrice(),
                'shipping' => $shippingTotal,
                'sum' => $amountTotal,
                'tax' => $taxTotal,
                'net' => $amountNet,
            ],
        ];
    }

    private function countItems(OrderEntity $order): int
    {
        $totalItems = 0;
        foreach ($order->getLineItems() ?? [] as $lineItem) {
            $totalItems += $lineItem->getQuantity();
        }

        return $totalItems;
    }

    private function buildFakeOrderPayload(
        ?string $channel,
        ?string $search,
        int $page,
        int $limit,
        string $sortField,
        string $sortDirection
    ): ?array
    {
        $orders = [
            [
                'id' => 'fake-1001',
                'channel' => 'zonami',
                'orderNumber' => 'EO-1001',
                'customerName' => 'Anna Müller',
                'orderReference' => 'A-3901',
                'email' => 'anna.mueller@example.com',
                'date' => '2024-06-18 09:14',
                'status' => 'processing',
                'statusLabel' => 'In Bearbeitung',
                'totalItems' => 3,
                'totalRevenue' => 420.55,
            ],
            [
                'id' => 'fake-1002',
                'channel' => 'ebay_de',
                'orderNumber' => 'EO-1002',
                'customerName' => 'Louis Schmidt',
                'orderReference' => 'A-3902',
                'email' => 'louis.schmidt@example.com',
                'date' => '2024-06-17 15:42',
                'status' => 'shipped',
                'statusLabel' => 'Versendet',
                'totalItems' => 2,
                'totalRevenue' => 310.2,
            ],
            [
                'id' => 'fake-1003',
                'channel' => 'kaufland',
                'orderNumber' => 'EO-1003',
                'customerName' => 'Sofia Weber',
                'orderReference' => 'A-3903',
                'email' => 'sofia.weber@example.com',
                'date' => '2024-06-16 11:03',
                'status' => 'closed',
                'statusLabel' => 'Abgeschlossen',
                'totalItems' => 3,
                'totalRevenue' => 559.7,
            ],
            [
                'id' => 'fake-1004',
                'channel' => 'amazon_de',
                'orderNumber' => 'EO-1004',
                'customerName' => 'Janine Koch',
                'orderReference' => 'A-3904',
                'email' => 'janine.koch@example.com',
                'date' => '2024-06-15 10:25',
                'status' => 'processing',
                'statusLabel' => 'In Bearbeitung',
                'totalItems' => 4,
                'totalRevenue' => 289.95,
            ],
            [
                'id' => 'fake-1005',
                'channel' => 'shopware',
                'orderNumber' => 'EO-1005',
                'customerName' => 'Matteo Rossi',
                'orderReference' => 'A-3905',
                'email' => 'matteo.rossi@example.com',
                'date' => '2024-06-14 17:58',
                'status' => 'cancelled',
                'statusLabel' => 'Storniert',
                'totalItems' => 1,
                'totalRevenue' => 89.0,
            ],
        ];

        if ($channel) {
            $orders = array_values(array_filter(
                $orders,
                static fn (array $order) => $order['channel'] === $channel
            ));
        }

        if ($search) {
            $orders = array_values(array_filter(
                $orders,
                static function (array $order) use ($search): bool {
                    $haystacks = [
                        $order['orderNumber'],
                        $order['customerName'],
                        $order['orderReference'],
                        $order['email'],
                    ];

                    foreach ($haystacks as $value) {
                        if (mb_stripos($value, $search) !== false) {
                            return true;
                        }
                    }

                    return false;
                }
            ));
        }

        $orders = $this->sortFakeOrders($orders, $sortField, $sortDirection);
        $total = count($orders);

        $orders = array_slice($orders, ($page - 1) * $limit, $limit);

        $totalRevenue = 0.0;
        $totalItems = 0;

        foreach ($orders as $order) {
            $totalRevenue += (float) $order['totalRevenue'];
            $totalItems += (int) $order['totalItems'];
        }

        $orders = array_map(
            static function (array $order): array {
                unset($order['totalRevenue']);

                return $order;
            },
            $orders
        );

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

    private function resolveSortField(?string $sort): string
    {
        $allowed = [
            'orderNumber' => 'orderNumber',
            'orderReference' => 'orderNumber',
            'customerName' => 'orderCustomer.lastName',
            'email' => 'orderCustomer.email',
            'date' => 'orderDateTime',
            'statusLabel' => 'stateMachineState.name',
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
    private function sortFakeOrders(array $orders, string $sortField, string $sortDirection): array
    {
        $map = [
            'orderNumber' => 'orderNumber',
            'orderDateTime' => 'date',
            'orderCustomer.lastName' => 'customerName',
            'orderCustomer.email' => 'email',
            'stateMachineState.name' => 'statusLabel',
        ];

        $key = $map[$sortField] ?? 'date';

        usort($orders, static function (array $left, array $right) use ($key, $sortDirection): int {
            $leftValue = $left[$key] ?? '';
            $rightValue = $right[$key] ?? '';

            if ($key === 'date') {
                $leftValue = strtotime((string) $leftValue) ?: 0;
                $rightValue = strtotime((string) $rightValue) ?: 0;
            }

            $comparison = $leftValue <=> $rightValue;

            return $sortDirection === FieldSorting::ASCENDING ? $comparison : -$comparison;
        });

        return $orders;
    }

    private function buildFakeOrderDetail(string $orderId): ?array
    {
        $details = [
            'fake-1001' => [
                'orderNumber' => 'EO-1001',
                'customer' => [
                    'number' => 'K-1001',
                    'firstName' => 'Anna',
                    'lastName' => 'Müller',
                    'email' => 'anna.mueller@example.com',
                    'group' => 'B2B',
                ],
                'billingAddress' => [
                    'company' => 'Müller Medizintechnik',
                    'street' => 'Hauptstraße 12',
                    'zip' => '70173',
                    'city' => 'Stuttgart',
                    'country' => 'Deutschland',
                ],
                'shippingAddress' => [
                    'name' => 'Anna Müller',
                    'street' => 'Hauptstraße 12',
                    'zipCity' => '70173 Stuttgart',
                    'country' => 'Deutschland',
                ],
                'payment' => [
                    'method' => 'Rechnung',
                    'code' => 'invoice',
                    'dueDate' => '2024-07-10',
                    'outstanding' => '0,00 €',
                    'settled' => 'Bezahlt',
                    'extra' => 'Skonto 2%',
                ],
                'shipping' => [
                    'method' => 'DHL',
                    'carrier' => 'DHL',
                    'trackingNumbers' => ['JD0146000101'],
                ],
                'additional' => [
                    'orderDate' => '2024-06-18 09:14',
                    'status' => 'In Bearbeitung',
                    'orderType' => 'Standard',
                    'notes' => 'Bitte vor 12 Uhr liefern.',
                    'consultant' => 'T. Schneider',
                    'tenant' => 'Zonami',
                    'san6OrderNumber' => 'SAN6-1001',
                    'orgaEntries' => ['ORG-112'],
                    'documents' => ['Rezept', 'Auftrag'],
                    'pdmsId' => 'PDMS-9001',
                    'pdmsVariant' => 'V2',
                    'topmArticleNumber' => 'TM-1001',
                    'topmExecution' => 'Standard',
                    'statusHistorySource' => 'Faker',
                ],
                'items' => [
                    [
                        'name' => 'Kompressionsstrümpfe',
                        'quantity' => 1,
                        'netPrice' => 110.0,
                        'taxRate' => 19,
                        'grossPrice' => 130.9,
                        'totalPrice' => 130.9,
                    ],
                    [
                        'name' => 'Bandage Set',
                        'quantity' => 2,
                        'netPrice' => 105.0,
                        'taxRate' => 19,
                        'grossPrice' => 124.85,
                        'totalPrice' => 249.7,
                    ],
                ],
                'statusHistory' => [
                    [
                        'status' => 'In Bearbeitung',
                        'date' => '2024-06-18 09:14',
                        'comment' => 'Auftrag angelegt',
                    ],
                ],
                'totals' => [
                    'items' => 380.0,
                    'shipping' => 12.5,
                    'sum' => 420.55,
                    'tax' => 40.55,
                    'net' => 380.0,
                ],
            ],
            'fake-1002' => [
                'orderNumber' => 'EO-1002',
                'customer' => [
                    'number' => 'K-1002',
                    'firstName' => 'Louis',
                    'lastName' => 'Schmidt',
                    'email' => 'louis.schmidt@example.com',
                    'group' => 'Retail',
                ],
                'billingAddress' => [
                    'company' => 'Schmidt Orthopädie',
                    'street' => 'Bergstraße 8',
                    'zip' => '80331',
                    'city' => 'München',
                    'country' => 'Deutschland',
                ],
                'shippingAddress' => [
                    'name' => 'Louis Schmidt',
                    'street' => 'Bergstraße 8',
                    'zipCity' => '80331 München',
                    'country' => 'Deutschland',
                ],
                'payment' => [
                    'method' => 'PayPal',
                    'code' => 'paypal',
                    'dueDate' => '2024-06-17',
                    'outstanding' => '0,00 €',
                    'settled' => 'Bezahlt',
                    'extra' => 'Transaktion bestätigt',
                ],
                'shipping' => [
                    'method' => 'GLS',
                    'carrier' => 'GLS',
                    'trackingNumbers' => ['GLS23450098'],
                ],
                'additional' => [
                    'orderDate' => '2024-06-17 15:42',
                    'status' => 'Versendet',
                    'orderType' => 'Express',
                    'notes' => 'Kontakt vor Lieferung.',
                    'consultant' => 'L. Richter',
                    'tenant' => 'Ebay.de',
                    'san6OrderNumber' => 'SAN6-1002',
                    'orgaEntries' => ['ORG-115'],
                    'documents' => ['Rechnung'],
                    'pdmsId' => 'PDMS-9002',
                    'pdmsVariant' => 'V1',
                    'topmArticleNumber' => 'TM-1002',
                    'topmExecution' => 'Express',
                    'statusHistorySource' => 'Faker',
                ],
                'items' => [
                    [
                        'name' => 'Orthese Knie',
                        'quantity' => 1,
                        'netPrice' => 180.0,
                        'taxRate' => 19,
                        'grossPrice' => 214.2,
                        'totalPrice' => 214.2,
                    ],
                    [
                        'name' => 'Pflegeset',
                        'quantity' => 1,
                        'netPrice' => 80.0,
                        'taxRate' => 19,
                        'grossPrice' => 95.2,
                        'totalPrice' => 95.2,
                    ],
                ],
                'statusHistory' => [
                    [
                        'status' => 'Versendet',
                        'date' => '2024-06-17 16:10',
                        'comment' => 'Paket an GLS übergeben.',
                    ],
                ],
                'totals' => [
                    'items' => 260.0,
                    'shipping' => 8.0,
                    'sum' => 310.2,
                    'tax' => 50.2,
                    'net' => 260.0,
                ],
            ],
            'fake-1003' => [
                'orderNumber' => 'EO-1003',
                'customer' => [
                    'number' => 'K-1003',
                    'firstName' => 'Sofia',
                    'lastName' => 'Weber',
                    'email' => 'sofia.weber@example.com',
                    'group' => 'Retail',
                ],
                'billingAddress' => [
                    'company' => 'Weber Care',
                    'street' => 'Gartenweg 3',
                    'zip' => '20095',
                    'city' => 'Hamburg',
                    'country' => 'Deutschland',
                ],
                'shippingAddress' => [
                    'name' => 'Sofia Weber',
                    'street' => 'Gartenweg 3',
                    'zipCity' => '20095 Hamburg',
                    'country' => 'Deutschland',
                ],
                'payment' => [
                    'method' => 'Kreditkarte',
                    'code' => 'credit-card',
                    'dueDate' => '2024-06-16',
                    'outstanding' => '0,00 €',
                    'settled' => 'Bezahlt',
                    'extra' => 'Mastercard',
                ],
                'shipping' => [
                    'method' => 'UPS',
                    'carrier' => 'UPS',
                    'trackingNumbers' => ['1Z999AA10123456784'],
                ],
                'additional' => [
                    'orderDate' => '2024-06-16 11:03',
                    'status' => 'Abgeschlossen',
                    'orderType' => 'Standard',
                    'notes' => 'Bitte an Rezeption liefern.',
                    'consultant' => 'M. Neumann',
                    'tenant' => 'Kaufland',
                    'san6OrderNumber' => 'SAN6-1003',
                    'orgaEntries' => ['ORG-118'],
                    'documents' => ['Rechnung', 'Lieferschein'],
                    'pdmsId' => 'PDMS-9003',
                    'pdmsVariant' => 'V3',
                    'topmArticleNumber' => 'TM-1003',
                    'topmExecution' => 'Standard',
                    'statusHistorySource' => 'Faker',
                ],
                'items' => [
                    [
                        'name' => 'Rollator',
                        'quantity' => 1,
                        'netPrice' => 420.0,
                        'taxRate' => 19,
                        'grossPrice' => 499.8,
                        'totalPrice' => 499.8,
                    ],
                    [
                        'name' => 'Zubehörset',
                        'quantity' => 2,
                        'netPrice' => 25.0,
                        'taxRate' => 19,
                        'grossPrice' => 29.95,
                        'totalPrice' => 59.9,
                    ],
                ],
                'statusHistory' => [
                    [
                        'status' => 'Abgeschlossen',
                        'date' => '2024-06-16 12:30',
                        'comment' => 'Vorgang abgeschlossen.',
                    ],
                ],
                'totals' => [
                    'items' => 470.0,
                    'shipping' => 9.9,
                    'sum' => 559.7,
                    'tax' => 89.7,
                    'net' => 470.0,
                ],
            ],
            'fake-1004' => [
                'orderNumber' => 'EO-1004',
                'customer' => [
                    'number' => 'K-1004',
                    'firstName' => 'Janine',
                    'lastName' => 'Koch',
                    'email' => 'janine.koch@example.com',
                    'group' => 'Retail',
                ],
                'billingAddress' => [
                    'company' => 'Koch Health',
                    'street' => 'Sonnenallee 21',
                    'zip' => '50667',
                    'city' => 'Köln',
                    'country' => 'Deutschland',
                ],
                'shippingAddress' => [
                    'name' => 'Janine Koch',
                    'street' => 'Sonnenallee 21',
                    'zipCity' => '50667 Köln',
                    'country' => 'Deutschland',
                ],
                'payment' => [
                    'method' => 'SEPA Lastschrift',
                    'code' => 'sepa',
                    'dueDate' => '2024-06-20',
                    'outstanding' => '0,00 €',
                    'settled' => 'Bezahlt',
                    'extra' => 'Mandat bestätigt',
                ],
                'shipping' => [
                    'method' => 'DHL',
                    'carrier' => 'DHL',
                    'trackingNumbers' => ['JD0146000104'],
                ],
                'additional' => [
                    'orderDate' => '2024-06-15 10:25',
                    'status' => 'In Bearbeitung',
                    'orderType' => 'Standard',
                    'notes' => 'Bitte telefonisch avisieren.',
                    'consultant' => 'S. Peters',
                    'tenant' => 'Amazon.de',
                    'san6OrderNumber' => 'SAN6-1004',
                    'orgaEntries' => ['ORG-121'],
                    'documents' => ['Auftrag'],
                    'pdmsId' => 'PDMS-9004',
                    'pdmsVariant' => 'V1',
                    'topmArticleNumber' => 'TM-1004',
                    'topmExecution' => 'Standard',
                    'statusHistorySource' => 'Faker',
                ],
                'items' => [
                    [
                        'name' => 'Therapieband',
                        'quantity' => 2,
                        'netPrice' => 30.0,
                        'taxRate' => 19,
                        'grossPrice' => 71.4,
                        'totalPrice' => 71.4,
                    ],
                    [
                        'name' => 'Ergo Griffpolster',
                        'quantity' => 2,
                        'netPrice' => 80.0,
                        'taxRate' => 19,
                        'grossPrice' => 190.4,
                        'totalPrice' => 190.4,
                    ],
                ],
                'statusHistory' => [
                    [
                        'status' => 'In Bearbeitung',
                        'date' => '2024-06-15 10:25',
                        'comment' => 'Bestellung eingegangen.',
                    ],
                ],
                'totals' => [
                    'items' => 260.0,
                    'shipping' => 29.95,
                    'sum' => 289.95,
                    'tax' => 29.95,
                    'net' => 260.0,
                ],
            ],
            'fake-1005' => [
                'orderNumber' => 'EO-1005',
                'customer' => [
                    'number' => 'K-1005',
                    'firstName' => 'Matteo',
                    'lastName' => 'Rossi',
                    'email' => 'matteo.rossi@example.com',
                    'group' => 'B2C',
                ],
                'billingAddress' => [
                    'company' => 'Rossi Sport',
                    'street' => 'Lindenstraße 77',
                    'zip' => '10115',
                    'city' => 'Berlin',
                    'country' => 'Deutschland',
                ],
                'shippingAddress' => [
                    'name' => 'Matteo Rossi',
                    'street' => 'Lindenstraße 77',
                    'zipCity' => '10115 Berlin',
                    'country' => 'Deutschland',
                ],
                'payment' => [
                    'method' => 'Kauf auf Rechnung',
                    'code' => 'invoice',
                    'dueDate' => '2024-06-28',
                    'outstanding' => '89,00 €',
                    'settled' => 'Offen',
                    'extra' => 'Zahlungsziel 14 Tage',
                ],
                'shipping' => [
                    'method' => 'Hermes',
                    'carrier' => 'Hermes',
                    'trackingNumbers' => ['HERMES403021'],
                ],
                'additional' => [
                    'orderDate' => '2024-06-14 17:58',
                    'status' => 'Storniert',
                    'orderType' => 'Standard',
                    'notes' => 'Kunde hat storniert.',
                    'consultant' => 'A. König',
                    'tenant' => 'Shopware',
                    'san6OrderNumber' => 'SAN6-1005',
                    'orgaEntries' => ['ORG-124'],
                    'documents' => ['Stornobeleg'],
                    'pdmsId' => 'PDMS-9005',
                    'pdmsVariant' => 'V4',
                    'topmArticleNumber' => 'TM-1005',
                    'topmExecution' => 'Standard',
                    'statusHistorySource' => 'Faker',
                ],
                'items' => [
                    [
                        'name' => 'Balance Board',
                        'quantity' => 1,
                        'netPrice' => 74.79,
                        'taxRate' => 19,
                        'grossPrice' => 89.0,
                        'totalPrice' => 89.0,
                    ],
                ],
                'statusHistory' => [
                    [
                        'status' => 'Storniert',
                        'date' => '2024-06-14 18:30',
                        'comment' => 'Bestellung storniert.',
                    ],
                ],
                'totals' => [
                    'items' => 74.79,
                    'shipping' => 14.21,
                    'sum' => 89.0,
                    'tax' => 14.21,
                    'net' => 74.79,
                ],
            ],
        ];

        return $details[$orderId] ?? null;
    }
}
