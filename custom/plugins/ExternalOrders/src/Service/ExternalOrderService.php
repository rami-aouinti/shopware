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
}
