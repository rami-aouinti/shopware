<?php declare(strict_types=1);

namespace ExternalOrders\Service;

use Shopware\Core\Framework\Context;

class ExternalOrderService
{
    public function fetchOrders(Context $context, ?string $channel = null, ?string $search = null): array
    {
        $orders = $this->getSampleOrders();

        if ($channel !== null && $channel !== '') {
            $orders = array_filter($orders, static fn (array $order): bool => $order['channel'] === $channel);
        }

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $orders = array_filter($orders, static function (array $order) use ($needle): bool {
                return str_contains(mb_strtolower($order['orderNumber']), $needle)
                    || str_contains(mb_strtolower($order['customerName']), $needle)
                    || str_contains(mb_strtolower($order['email']), $needle)
                    || str_contains(mb_strtolower($order['orderReference']), $needle);
            });
        }

        $orders = array_values($orders);

        return [
            'summary' => $this->buildSummary($orders),
            'orders' => $orders,
        ];
    }

    public function fetchOrderDetail(Context $context, string $orderId): ?array
    {
        foreach ($this->getSampleOrders() as $order) {
            if ($order['id'] === $orderId) {
                return $order['detail'];
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getSampleOrders(): array
    {
        return [
            [
                'id' => 'order-1008722',
                'channel' => 'ebay_de',
                'orderNumber' => '1008722',
                'customerName' => 'Andreas Nanke',
                'orderReference' => '446487',
                'email' => '43ad1d549beae3625950@members.ebay.com',
                'date' => '2025-12-30 09:31',
                'status' => 'processing',
                'statusLabel' => 'Bezahlt / in Bearbeitung',
                'totalItems' => 1,
                'detail' => $this->buildSampleDetail(
                    '1008722',
                    'Andreas',
                    'Nanke',
                    'Genossenschaftsstraße 13',
                    '29356',
                    'Bröckel',
                    'Germany',
                    'ebay',
                    '446487'
                ),
            ],
            [
                'id' => 'order-1008721',
                'channel' => 'ebay_de',
                'orderNumber' => '1008721',
                'customerName' => 'Frank Sagert',
                'orderReference' => '446480',
                'email' => '010eb3cea0c0a1c80a10@members.ebay.com',
                'date' => '2025-12-30 08:46',
                'status' => 'processing',
                'statusLabel' => 'Bezahlt / in Bearbeitung',
                'totalItems' => 2,
                'detail' => $this->buildSampleDetail(
                    '1008721',
                    'Frank',
                    'Sagert',
                    'Kabelweg 9',
                    '22299',
                    'Hamburg',
                    'Germany',
                    'ebay',
                    '446480'
                ),
            ],
            [
                'id' => 'order-1008716',
                'channel' => 'kaufland',
                'orderNumber' => '1008716',
                'customerName' => 'Karsten Stieler',
                'orderReference' => '446447',
                'email' => '43aab48bab92e321f662@members.kaufland.de',
                'date' => '2025-12-29 22:33',
                'status' => 'processing',
                'statusLabel' => 'Bezahlt / in Bearbeitung',
                'totalItems' => 1,
                'detail' => $this->buildSampleDetail(
                    '1008716',
                    'Karsten',
                    'Stieler',
                    'Marienstraße 12',
                    '40213',
                    'Düsseldorf',
                    'Germany',
                    'kaufland',
                    '446447'
                ),
            ],
        ];
    }

    private function buildSummary(array $orders): array
    {
        $totalItems = array_sum(array_map(static fn (array $order): int => $order['totalItems'], $orders));

        return [
            'orderCount' => count($orders),
            'totalRevenue' => 1584.19,
            'totalItems' => $totalItems,
        ];
    }

    private function buildSampleDetail(
        string $orderNumber,
        string $firstName,
        string $lastName,
        string $street,
        string $zip,
        string $city,
        string $country,
        string $shippingMethod,
        string $reference
    ): array {
        return [
            'orderNumber' => $orderNumber,
            'customer' => [
                'number' => 'N/A',
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => strtolower($firstName) . '.' . strtolower($lastName) . '@example.com',
                'group' => 'E-Commerce',
            ],
            'billingAddress' => [
                'company' => 'N/A',
                'street' => $street,
                'zip' => $zip,
                'city' => $city,
                'country' => $country,
            ],
            'shippingAddress' => [
                'name' => $firstName . ' ' . $lastName,
                'street' => $street,
                'zipCity' => $zip . ' ' . $city,
                'country' => $country,
            ],
            'payment' => [
                'method' => $shippingMethod,
                'code' => 'N/A',
                'dueDate' => 'N/A',
                'outstanding' => 'N/A',
                'settled' => 'N/A',
                'extra' => 'N/A',
            ],
            'shipping' => [
                'method' => $shippingMethod,
                'carrier' => 'DHL',
                'trackingNumbers' => ['003404342343', '003404342344'],
            ],
            'additional' => [
                'orderDate' => '2025-12-30 09:31',
                'status' => 'Bezahlt / in Bearbeitung',
                'orderType' => 'N/A',
                'notes' => 'N/A',
                'consultant' => 'N/A',
                'tenant' => 'N/A',
                'san6OrderNumber' => 'SAN6-' . $reference,
                'orgaEntries' => ['Orga 1043', 'Orga 7784'],
                'documents' => ['Rechnung_1008722.pdf', 'Lieferschein_1008722.pdf'],
                'pdmsId' => 'PDMS-19444',
                'pdmsVariant' => 'Variante 3',
                'topmArticleNumber' => 'TOPM-9443',
                'topmExecution' => 'Ausführung A',
                'statusHistorySource' => 'Shopware',
            ],
            'items' => [
                [
                    'name' => 'HYPAFIX Hautfreundliches Klebevlies',
                    'quantity' => 1,
                    'netPrice' => 19.32,
                    'taxRate' => 19,
                    'grossPrice' => 22.99,
                    'totalPrice' => 22.99,
                ],
            ],
            'statusHistory' => [
                [
                    'status' => 'Bezahlt / in Bearbeitung',
                    'date' => '2025-12-30 09:31',
                    'comment' => 'magnalister-Verarbeitung (eBay) eBayOrderID: 205396841131',
                ],
                [
                    'status' => 'INTERN (Bearbeitung offen)',
                    'date' => '2025-12-30 09:45',
                    'comment' => 'Auftragsnr.:' . $reference,
                ],
                [
                    'status' => 'Versendet',
                    'date' => '2026-01-08 16:03',
                    'comment' => 'Trackingnummer: 003404342343',
                ],
                [
                    'status' => 'Bestellung abgeschlossen',
                    'date' => '2026-01-13 16:02',
                    'comment' => 'N/A',
                ],
            ],
            'totals' => [
                'items' => 22.99,
                'shipping' => 0.0,
                'sum' => 22.99,
                'tax' => 3.67,
                'net' => 19.32,
            ],
        ];
    }
}
