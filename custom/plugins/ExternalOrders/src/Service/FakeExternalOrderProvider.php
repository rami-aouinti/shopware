<?php declare(strict_types=1);

namespace ExternalOrders\Service;

class FakeExternalOrderProvider
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSeedPayloads(): array
    {
        $orders = $this->getFakeOrders();
        $details = $this->getFakeOrderDetails();

        $payloads = [];
        foreach ($orders as $order) {
            $detail = $details[$order['id']] ?? null;
            $payloads[] = array_merge(
                $order,
                $detail ? ['detail' => $detail] : []
            );
        }

        return $payloads;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFakeOrders(): array
    {
        return [
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
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getFakeOrderDetails(): array
    {
        return [
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
                    'shipping' => 9.95,
                    'sum' => 289.95,
                    'tax' => 45.95,
                    'net' => 244.0,
                ],
            ],
            'fake-1005' => [
                'orderNumber' => 'EO-1005',
                'customer' => [
                    'number' => 'K-1005',
                    'firstName' => 'Matteo',
                    'lastName' => 'Rossi',
                    'email' => 'matteo.rossi@example.com',
                    'group' => 'B2B',
                ],
                'billingAddress' => [
                    'company' => 'Rossi Care',
                    'street' => 'Via Roma 10',
                    'zip' => '10121',
                    'city' => 'Torino',
                    'country' => 'Italien',
                ],
                'shippingAddress' => [
                    'name' => 'Matteo Rossi',
                    'street' => 'Via Roma 10',
                    'zipCity' => '10121 Torino',
                    'country' => 'Italien',
                ],
                'payment' => [
                    'method' => 'Vorkasse',
                    'code' => 'prepayment',
                    'dueDate' => '2024-06-19',
                    'outstanding' => '89,00 €',
                    'settled' => 'Ausstehend',
                    'extra' => 'Banktransfer',
                ],
                'shipping' => [
                    'method' => 'Poste Italiane',
                    'carrier' => 'Poste Italiane',
                    'trackingNumbers' => ['IT1234567890'],
                ],
                'additional' => [
                    'orderDate' => '2024-06-14 17:58',
                    'status' => 'Storniert',
                    'orderType' => 'Standard',
                    'notes' => 'Kunde storniert.',
                    'consultant' => 'M. Rossi',
                    'tenant' => 'Shopware',
                    'san6OrderNumber' => 'SAN6-1005',
                    'orgaEntries' => ['ORG-125'],
                    'documents' => ['Storno'],
                    'pdmsId' => 'PDMS-9005',
                    'pdmsVariant' => 'V1',
                    'topmArticleNumber' => 'TM-1005',
                    'topmExecution' => 'Standard',
                    'statusHistorySource' => 'Faker',
                ],
                'items' => [
                    [
                        'name' => 'Orthopädisches Kissen',
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
                        'date' => '2024-06-14 18:10',
                        'comment' => 'Storno durch Kunden.',
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
    }
}
