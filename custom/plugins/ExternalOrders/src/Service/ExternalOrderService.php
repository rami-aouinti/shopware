<?php declare(strict_types=1);

namespace ExternalOrders\Service;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class ExternalOrderService
{
    public function __construct(private readonly EntityRepository $orderRepository)
    {
    }

    public function fetchOrders(Context $context, ?string $channel = null, ?string $search = null): array
    {
        $fakePayload = $this->buildFakeOrderPayload($channel, $search);
        if ($fakePayload !== null) {
            return $fakePayload;
        }

        $criteria = new Criteria();
        $criteria->setLimit(50);
        $criteria->addSorting(new FieldSorting('orderDateTime', FieldSorting::DESCENDING));
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

        foreach ($this->orderRepository->search($criteria, $context)->getEntities() as $order) {
            $orders[] = $this->mapOrderListItem($order);
            $totalRevenue += (float) $order->getAmountTotal();
            $totalItems += $this->countItems($order);
        }

        return [
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
        $fakeDetail = $this->buildFakeOrderDetail($orderId);
        if ($fakeDetail !== null) {
            return $fakeDetail;
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
            if ($price && $price->getCalculatedTaxes()->count() > 0) {
                $taxRate = $price->getCalculatedTaxes()->first()->getTaxRate();
            }

            $items[] = [
                'name' => $lineItem->getLabel() ?? $lineItem->getId(),
                'quantity' => $lineItem->getQuantity(),
                'netPrice' => $price?->getNetPrice() ?? 0.0,
                'taxRate' => $taxRate,
                'grossPrice' => $price?->getTotalPrice() ?? 0.0,
                'totalPrice' => $price?->getTotalPrice() ?? 0.0,
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

        return [
            'orderNumber' => $order->getOrderNumber(),
            'customer' => [
                'number' => $orderCustomer?->getCustomerNumber() ?? 'N/A',
                'firstName' => $orderCustomer?->getFirstName() ?? 'N/A',
                'lastName' => $orderCustomer?->getLastName() ?? 'N/A',
                'email' => $orderCustomer?->getEmail() ?? 'N/A',
                'group' => $orderCustomer?->getCustomerGroup()?->getName() ?? 'N/A',
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

    private function buildFakeOrderPayload(?string $channel, ?string $search): ?array
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
            'summary' => [
                'orderCount' => count($orders),
                'totalRevenue' => $totalRevenue,
                'totalItems' => $totalItems,
            ],
            'orders' => $orders,
        ];
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
        ];

        return $details[$orderId] ?? null;
    }
}
