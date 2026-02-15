<?php declare(strict_types=1);

namespace ExternalOrders\Service;

class FakeExternalOrderProvider
{
    /**
     * Official demo external order ID prefix shared with LieferzeitenAdmin demo seeding.
     */
    public const DEMO_ORDER_PREFIX = 'DEMO-';

    private const CHANNELS = [
        'b2b',
        'ebay_de',
        'kaufland',
        'ebay_at',
        'zonami',
        'peg',
        'bezb',
    ];

    private const FIRST_NAMES = [
        'Anna',
        'Louis',
        'Sofia',
        'Janine',
        'Matteo',
        'Lea',
        'Felix',
        'Noah',
        'Lina',
        'Mara',
        'Paul',
        'Milan',
        'Clara',
        'Jonas',
        'Nina',
    ];

    private const LAST_NAMES = [
        'Müller',
        'Schmidt',
        'Weber',
        'Koch',
        'Rossi',
        'Fischer',
        'Schneider',
        'Wagner',
        'Bauer',
        'Zimmermann',
        'Hoffmann',
        'Richter',
        'Wolf',
        'Krüger',
        'Peters',
    ];

    private const CITIES = [
        'Berlin',
        'Hamburg',
        'München',
        'Köln',
        'Frankfurt',
        'Stuttgart',
        'Düsseldorf',
        'Leipzig',
        'Bremen',
        'Dresden',
        'Hannover',
    ];

    private const COUNTRIES = [
        'Deutschland',
        'Österreich',
        'Schweiz',
        'Luxemburg',
    ];

    private const STREETS = [
        'Hauptstraße',
        'Bergstraße',
        'Schillerstraße',
        'Bahnhofstraße',
        'Gartenweg',
        'Schulstraße',
        'Parkallee',
        'Am Markt',
        'Lindenweg',
        'Kirchplatz',
    ];

    private const PAYMENT_METHODS = [
        'Rechnung',
        'PayPal',
        'Kreditkarte',
        'SEPA-Lastschrift',
        'Vorkasse',
    ];

    private const DETAIL_TEMPLATES = [
        [
            'paymentMethod' => 'ebay',
            'shippingMethod' => 'ebay',
            'items' => [
                [
                    'productName' => 'HYPAFIX Hautfreundliches Klebevlies Flexibel Anpassbar Luftdurchlässig Fixierung[ohne Rezept,15 cm x 10 m,Mit geschnittenem Abdeckpapier]',
                    'quantity' => 1,
                    'finalPrice' => 22.99,
                    'productPrice' => 0.0,
                    'productTax' => 0.0,
                ],
            ],
            'totals' => [
                ['title' => 'Warenwert:', 'value' => '22.9900', 'text' => '22,99 EUR'],
                ['title' => 'Versandkosten:', 'value' => '0.0000', 'text' => '0,00 EUR'],
                ['title' => '<b>Summe</b>:', 'value' => '22.9900', 'text' => '22,99 EUR'],
                ['title' => 'inkl. 19% MwSt.:', 'value' => '3.6707', 'text' => '3,67 EUR'],
                ['title' => 'Summe netto:', 'value' => '19.3193', 'text' => '19,32 EUR'],
            ],
            'customer' => [
                'id' => 9556,
                'cid' => 'N/A',
                'vatId' => 'N/A',
                'status' => 12,
                'statusName' => 'Kundengruppe Ebay DE',
                'statusImage' => '',
                'statusDiscount' => 0.00,
                'name' => 'Andreas Nanke',
                'firstName' => 'Andreas',
                'lastName' => 'Nanke',
                'gender' => '',
                'company' => '',
                'streetAddress' => 'Genossenschaftsstraße 13',
                'houseNumber' => '',
                'additionalInfo' => '',
                'suburb' => 'N/A',
                'city' => 'Bröckel',
                'postcode' => '29356',
                'state' => '',
                'country' => 'Germany',
                'telephone' => '',
                'emailAddress' => '43ad1d549beae3625950@members.ebay.com',
                'addressFormatId' => 5,
            ],
            'billing' => [
                'name' => 'Andreas Nanke',
                'firstName' => 'Andreas',
                'lastName' => 'Nanke',
                'gender' => '',
                'company' => '',
                'streetAddress' => 'Genossenschaftsstraße 13',
                'houseNumber' => '',
                'additionalInfo' => '',
                'suburb' => 'N/A',
                'city' => 'Bröckel',
                'postcode' => '29356',
                'state' => '',
                'country' => 'Germany',
                'countryIsoCode2' => 'DE',
                'addressFormatId' => 5,
            ],
            'delivery' => [
                'name' => 'Andreas Nanke',
                'firstName' => 'Andreas',
                'lastName' => 'Nanke',
                'gender' => '',
                'company' => '',
                'streetAddress' => 'Genossenschaftsstraße 13',
                'houseNumber' => '',
                'additionalInfo' => '',
                'suburb' => 'N/A',
                'city' => 'Bröckel',
                'postcode' => '29356',
                'state' => '',
                'country' => 'Germany',
                'countryIsoCode2' => 'DE',
                'addressFormatId' => 5,
            ],
        ],
        [
            'paymentMethod' => 'ebay',
            'shippingMethod' => 'ebay',
            'items' => [
                [
                    'productName' => 'PRÄZISA Einmal-Skalpelle 10er Set Steril Edelstahl Medizinische Chirurgie[Fig. 23]',
                    'quantity' => 1,
                    'finalPrice' => 9.99,
                    'productPrice' => 0.0,
                    'productTax' => 0.0,
                ],
            ],
            'totals' => [
                ['title' => 'Warenwert:', 'value' => '9.9900', 'text' => '9,99 EUR'],
                ['title' => 'Versandkosten:', 'value' => '0.0000', 'text' => '0,00 EUR'],
                ['title' => '<b>Summe</b>:', 'value' => '9.9900', 'text' => '9,99 EUR'],
                ['title' => 'inkl. 19% MwSt.:', 'value' => '1.5950', 'text' => '1,60 EUR'],
                ['title' => 'Summe netto:', 'value' => '8.3950', 'text' => '8,39 EUR'],
            ],
            'customer' => [
                'id' => 9509,
                'cid' => '',
                'vatId' => '',
                'status' => 12,
                'statusName' => 'Kundengruppe Ebay DE',
                'statusImage' => '',
                'statusDiscount' => 0.00,
                'name' => 'Peter Baisel',
                'firstName' => 'Peter',
                'lastName' => 'Baisel',
                'gender' => '',
                'company' => '',
                'streetAddress' => 'Kienbergstr. 22',
                'houseNumber' => '',
                'additionalInfo' => '',
                'suburb' => '',
                'city' => 'Traunstein',
                'postcode' => '83278',
                'state' => '',
                'country' => 'Germany',
                'telephone' => '',
                'emailAddress' => '439dd0cb31e0b2bff771@members.ebay.com',
                'addressFormatId' => 5,
            ],
            'billing' => [
                'name' => 'Peter Baisel',
                'firstName' => 'Peter',
                'lastName' => 'Baisel',
                'gender' => '',
                'company' => '',
                'streetAddress' => 'Kienbergstr. 22',
                'houseNumber' => '',
                'additionalInfo' => '',
                'suburb' => '',
                'city' => 'Traunstein',
                'postcode' => '83278',
                'state' => '',
                'country' => 'Germany',
                'countryIsoCode2' => 'DE',
                'addressFormatId' => 5,
            ],
            'delivery' => [
                'name' => 'Peter Baisel',
                'firstName' => 'Peter',
                'lastName' => 'Baisel',
                'gender' => '',
                'company' => '',
                'streetAddress' => 'Münchener Str. 20',
                'houseNumber' => '',
                'additionalInfo' => '',
                'suburb' => '',
                'city' => 'Traunreut',
                'postcode' => '83301',
                'state' => '',
                'country' => 'Germany',
                'countryIsoCode2' => 'DE',
                'addressFormatId' => 5,
            ],
        ],
        [
            'paymentMethod' => 'ebay',
            'shippingMethod' => 'ebay',
            'items' => [
                [
                    'productName' => 'RAPPAPORT Stethoskop Ersatz Membranen 35mm & 45mm Set Medizinisches Zubehör',
                    'quantity' => 1,
                    'finalPrice' => 7.99,
                    'productPrice' => 0.0,
                    'productTax' => 0.0,
                ],
                [
                    'productName' => 'RAPPAPORT Stethoskop Ersatzschläuche Schwarz Medizinisches Zubehör',
                    'quantity' => 1,
                    'finalPrice' => 10.99,
                    'productPrice' => 0.0,
                    'productTax' => 0.0,
                ],
            ],
            'totals' => [
                ['title' => 'Warenwert:', 'value' => '18.9800', 'text' => '18,98 EUR'],
                ['title' => 'Versandkosten:', 'value' => '0.0000', 'text' => '0,00 EUR'],
                ['title' => '<b>Summe</b>:', 'value' => '18.9800', 'text' => '18,98 EUR'],
                ['title' => 'inkl. 19% MwSt.:', 'value' => '3.0304', 'text' => '3,03 EUR'],
                ['title' => 'Summe netto:', 'value' => '15.9496', 'text' => '15,95 EUR'],
            ],
            'customer' => [
                'id' => 9443,
                'cid' => '',
                'vatId' => '',
                'status' => 12,
                'statusName' => 'Kundengruppe Ebay DE',
                'statusImage' => '',
                'statusDiscount' => 0.00,
                'name' => 'Atamanchuk Kostiantyn',
                'firstName' => 'Atamanchuk',
                'lastName' => 'Kostiantyn',
                'gender' => '',
                'company' => '',
                'streetAddress' => 'Vilsweg 3',
                'houseNumber' => '',
                'additionalInfo' => '',
                'suburb' => '',
                'city' => 'Neutraubling',
                'postcode' => '93073',
                'state' => '',
                'country' => 'Germany',
                'telephone' => '',
                'emailAddress' => '438411568442d3c52f46@members.ebay.com',
                'addressFormatId' => 5,
            ],
            'billing' => [
                'name' => 'Atamanchuk Kostiantyn',
                'firstName' => 'Atamanchuk',
                'lastName' => 'Kostiantyn',
                'gender' => '',
                'company' => '',
                'streetAddress' => 'Vilsweg 3',
                'houseNumber' => '',
                'additionalInfo' => '',
                'suburb' => '',
                'city' => 'Neutraubling',
                'postcode' => '93073',
                'state' => '',
                'country' => 'Germany',
                'countryIsoCode2' => 'DE',
                'addressFormatId' => 5,
            ],
            'delivery' => [
                'name' => 'Kostiantyn Atamanchuk',
                'firstName' => 'Kostiantyn',
                'lastName' => 'Atamanchuk',
                'gender' => '',
                'company' => '',
                'streetAddress' => 'Vilsweg 3',
                'houseNumber' => '',
                'additionalInfo' => '',
                'suburb' => '',
                'city' => 'Neutraubling',
                'postcode' => '93073',
                'state' => '',
                'country' => 'Germany',
                'countryIsoCode2' => 'DE',
                'addressFormatId' => 5,
            ],
        ],
        [
            'paymentMethod' => 'ebay',
            'shippingMethod' => 'ebay',
            'items' => [
                [
                    'productName' => 'Sterican Tief-Intramuskulär Einmalkanülen 100St Chrom-Nickel Stahl Sonderkanülen[G 20 x 2 3/4"]',
                    'quantity' => 1,
                    'finalPrice' => 12.99,
                    'productPrice' => 0.0,
                    'productTax' => 0.0,
                ],
            ],
            'totals' => [
                ['title' => 'Warenwert:', 'value' => '12.9900', 'text' => '12,99 EUR'],
                ['title' => 'Versandkosten:', 'value' => '0.0000', 'text' => '0,00 EUR'],
                ['title' => '<b>Summe</b>:', 'value' => '12.9900', 'text' => '12,99 EUR'],
                ['title' => 'inkl. 19% MwSt.:', 'value' => '2.0740', 'text' => '2,07 EUR'],
                ['title' => 'Summe netto:', 'value' => '10.9160', 'text' => '10,92 EUR'],
            ],
            'customer' => [
                'id' => 9428,
                'cid' => '',
                'vatId' => '',
                'status' => 12,
                'statusName' => 'Kundengruppe Ebay DE',
                'statusImage' => '',
                'statusDiscount' => 0.00,
                'name' => 'Andreas Papendick',
                'firstName' => 'Andreas',
                'lastName' => 'Papendick',
                'gender' => '',
                'company' => '',
                'streetAddress' => 'Kiebitzstr. 68',
                'houseNumber' => '',
                'additionalInfo' => '',
                'suburb' => '',
                'city' => 'Euskirchen',
                'postcode' => '53881',
                'state' => '',
                'country' => 'Germany',
                'telephone' => '',
                'emailAddress' => '43812aa24e2ad3724a73@members.ebay.com',
                'addressFormatId' => 5,
            ],
            'billing' => [
                'name' => 'Andreas Papendick',
                'firstName' => 'Andreas',
                'lastName' => 'Papendick',
                'gender' => '',
                'company' => '',
                'streetAddress' => 'Kiebitzstr. 68',
                'houseNumber' => '',
                'additionalInfo' => '',
                'suburb' => '',
                'city' => 'Euskirchen',
                'postcode' => '53881',
                'state' => '',
                'country' => 'Germany',
                'countryIsoCode2' => 'DE',
                'addressFormatId' => 5,
            ],
            'delivery' => [
                'name' => 'Andreas Papendick',
                'firstName' => 'Andreas',
                'lastName' => 'Papendick',
                'gender' => '',
                'company' => '',
                'streetAddress' => 'Packstation 102',
                'houseNumber' => '',
                'additionalInfo' => '23665827',
                'suburb' => '',
                'city' => 'Euskirchen',
                'postcode' => '53881',
                'state' => '',
                'country' => 'Germany',
                'countryIsoCode2' => 'DE',
                'addressFormatId' => 5,
            ],
        ],
    ];

    private const STATUS_DEFINITIONS = [
        ['code' => 'paid_processing', 'label' => 'Bezahlt / in Bearbeitung', 'color' => '2196f3'],
        ['code' => 'prepayment_open', 'label' => 'Vorkasse: Bezahlung offen', 'color' => 'f5e502'],
        ['code' => 'not_paid_processing', 'label' => 'Nicht bezahlt / In Bearbeitung', 'color' => '2196f3'],
        ['code' => 'shipped', 'label' => 'Versendet', 'color' => '45a845'],
        ['code' => 'order_completed', 'label' => 'Bestellung abgeschlossen', 'color' => '000000'],
        ['code' => 'internal_processing_open', 'label' => 'INTERN (Bearbeitung offen)', 'color' => null],
        ['code' => 'cancellation_completed', 'label' => 'Stornierung abgeschlossen', 'color' => '9e9e9e'],
        ['code' => 'internal_unconfirmed', 'label' => 'INTERN (Nicht bestätigt)', 'color' => null],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSeedPayloads(): array
    {
        $payloads = [];
        foreach ($this->getFakeOrders() as $order) {
            $payloads[] = array_merge($order, [
                'detail' => $this->buildFakeOrderDetail($order),
            ]);
        }

        return $payloads;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFakeOrders(): array
    {
        $orders = [];
        $perChannel = 100;

        foreach (self::CHANNELS as $channel) {
            for ($index = 1; $index <= $perChannel; $index += 1) {
                $id = sprintf('%s%s-%03d', self::DEMO_ORDER_PREFIX, strtoupper($channel), $index);
                $firstName = $this->pickValue(self::FIRST_NAMES, $id, 1);
                $lastName = $this->pickValue(self::LAST_NAMES, $id, 2);
                $customerName = sprintf('%s %s', $firstName, $lastName);
                $orderNumber = sprintf('EO-%s-%04d', strtoupper($channel), $index);
                $orderReference = sprintf('A-%s-%04d', strtoupper($channel), $index);
                $email = sprintf(
                    '%s.%s%03d@example.com',
                    $this->slugify($firstName),
                    $this->slugify($lastName),
                    $index
                );
                $statusDefinition = $this->pickStatusDefinition($id, 3);
                $status = $statusDefinition['code'];
                $statusLabel = $statusDefinition['label'];
                $totalItems = $this->randomInt($id, 1, 6, 4);
                $pricePerItem = $this->randomInt($id, 2500, 19500, 5) / 100;
                $totalRevenue = round($totalItems * $pricePerItem, 2);
                $orderId = 1008000 + ($index * 10) + $this->randomInt($id, 1, 9, 6);
                $auftragNumber = 446000 + $index;
                $isTestOrder = $this->randomInt($id, 0, 19, 7) === 0;

                $orders[] = [
                    'id' => $id,
                    'externalId' => $id,
                    'orderId' => $orderId,
                    'channel' => $channel,
                    'orderNumber' => $orderNumber,
                    'auftragNumber' => $auftragNumber,
                    'customerName' => $customerName,
                    'customersName' => $customerName,
                    'orderReference' => $orderReference,
                    'email' => $email,
                    'customersEmailAddress' => $email,
                    'date' => $this->randomOrderDate($id),
                    'datePurchased' => $this->randomOrderDateIso8601($id),
                    'status' => $status,
                    'statusLabel' => $statusLabel,
                    'ordersStatusName' => $statusLabel,
                    'orderStatusColor' => $statusDefinition['color'],
                    'isTestOrder' => $isTestOrder,
                    'totalItems' => $totalItems,
                    'totalRevenue' => $totalRevenue,
                ];
            }
        }

        return $orders;
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function buildFakeOrderDetail(array $order): array
    {
        $id = (string) ($order['id'] ?? '');
        $detailTemplate = $this->pickDetailTemplate($id);
        $orderDateIso = $order['datePurchased'] ?? $this->randomOrderDateIso8601($id);
        $statusLabel = $order['statusLabel'] ?? $order['ordersStatusName'] ?? 'Bezahlt / in Bearbeitung';
        $statusColor = $order['orderStatusColor'] ?? $this->resolveStatusColorByLabel($statusLabel);
        $customer = $detailTemplate['customer'];
        $email = (string) ($order['email'] ?? $customer['emailAddress']);
        $lastName = (string) ($customer['lastName'] ?? 'Unknown');
        $statusHistory = $this->buildStatusHistory(
            $id,
            $statusLabel,
            $orderDateIso,
            $lastName,
            (string) ($order['channel'] ?? 'ebay'),
            (string) ($order['auftragNumber'] ?? 'N/A')
        );

        return [
            'orderId' => (int) ($order['orderId'] ?? 0),
            'datePurchased' => $orderDateIso,
            'paymentMethod' => (string) $detailTemplate['paymentMethod'],
            'orderStatus' => $statusLabel,
            'orderStatusColor' => $statusColor,
            'shippingMethod' => (string) $detailTemplate['shippingMethod'],
            'auftragNumber' => (int) ($order['auftragNumber'] ?? 0),
            'items' => $detailTemplate['items'],
            'totals' => $detailTemplate['totals'],
            'statusHistory' => $statusHistory,
            'customer' => array_merge($customer, ['emailAddress' => $email]),
            'billing' => $detailTemplate['billing'],
            'delivery' => $detailTemplate['delivery'],
        ];
    }

    /**
     * @return array{paymentMethod: string, shippingMethod: string, items: array<int, array<string, mixed>>, totals: array<int, array<string, string>>, customer: array<string, mixed>, billing: array<string, mixed>, delivery: array<string, mixed>}
     */
    private function pickDetailTemplate(string $seed): array
    {
        $index = $this->randomInt($seed, 0, count(self::DETAIL_TEMPLATES) - 1, 40);

        return self::DETAIL_TEMPLATES[$index] ?? self::DETAIL_TEMPLATES[0];
    }

    /**
     * @return array<int, array{statusName: string, statusColor: string|null, dateAdded: string, comments: string}>
     */
    private function buildStatusHistory(
        string $id,
        string $statusLabel,
        string $orderDateIso,
        string $lastName,
        string $channel,
        string $auftragNumber
    ): array {
        $entries = [
            [
                'statusName' => $statusLabel,
                'statusColor' => $this->resolveStatusColorByLabel($statusLabel),
                'dateAdded' => $orderDateIso,
                'comments' => sprintf(
                    "magnalister-Verarbeitung (%s)\neBayOrderID: %d-%d\nExtendedOrderID: %02d-%05d-%05d\neBay User: %s",
                    strtoupper($channel),
                    $this->randomInt($id, 100000000000, 999999999999, 31),
                    $this->randomInt($id, 10000000000000, 99999999999999, 32),
                    $this->randomInt($id, 1, 99, 33),
                    $this->randomInt($id, 10000, 99999, 34),
                    $this->randomInt($id, 10000, 99999, 35),
                    $this->slugify($lastName)
                ),
            ],
        ];

        if ($statusLabel !== 'INTERN (Nicht bestätigt)' && $statusLabel !== 'Stornierung abgeschlossen') {
            $entries[] = [
                'statusName' => 'INTERN (Bearbeitung offen)',
                'statusColor' => null,
                'dateAdded' => $this->randomOrderDateIso8601($id, 1),
                'comments' => sprintf('Auftragsnr.:%s', $auftragNumber),
            ];
        }

        if (in_array($statusLabel, ['Versendet', 'Bestellung abgeschlossen'], true)) {
            $entries[] = [
                'statusName' => 'Versendet',
                'statusColor' => '45a845',
                'dateAdded' => $this->randomOrderDateIso8601($id, 2),
                'comments' => '',
            ];
        }

        if ($statusLabel === 'Bestellung abgeschlossen') {
            $entries[] = [
                'statusName' => 'Bestellung abgeschlossen',
                'statusColor' => '000000',
                'dateAdded' => $this->randomOrderDateIso8601($id, 3),
                'comments' => '',
            ];
        }

        if ($statusLabel === 'Stornierung abgeschlossen') {
            $entries[] = [
                'statusName' => 'Stornierung abgeschlossen',
                'statusColor' => '9e9e9e',
                'dateAdded' => $this->randomOrderDateIso8601($id, 1),
                'comments' => 'Vorgang storniert',
            ];
        }

        return $entries;
    }

    private function resolveStatusColorByLabel(string $statusLabel): ?string
    {
        foreach (self::STATUS_DEFINITIONS as $statusDefinition) {
            if (($statusDefinition['label'] ?? '') === $statusLabel) {
                return $statusDefinition['color'] ?? null;
            }
        }

        return null;
    }

    private function formatEuro(float $value): string
    {
        return number_format($value, 2, ',', '.') . ' EUR';
    }

    /**
     * @param array<int, string> $values
     */
    private function pickValue(array $values, string $seed, int $offset = 0): string
    {
        if ($values === []) {
            return '';
        }

        $index = $this->randomInt($seed, 0, count($values) - 1, $offset);
        return $values[$index] ?? $values[0];
    }

    private function randomInt(string $seed, int $min, int $max, int $offset = 0): int
    {
        if ($max <= $min) {
            return $min;
        }

        $hash = crc32($seed . ':' . $offset);
        return $min + ($hash % ($max - $min + 1));
    }

    private function randomOrderDate(string $seed, int $offsetDays = 0): string
    {
        $daysAgo = $this->randomInt($seed, 1 + $offsetDays, 120 + $offsetDays, 25);
        $secondsOffset = $this->randomInt($seed, 0, 86400, 26);
        $timestamp = time() - ($daysAgo * 86400) - $secondsOffset;

        return date('Y-m-d H:i', $timestamp);
    }

    private function randomOrderDateIso8601(string $seed, int $offsetDays = 0): string
    {
        $daysAgo = $this->randomInt($seed, 1 + $offsetDays, 120 + $offsetDays, 27);
        $secondsOffset = $this->randomInt($seed, 0, 86400, 28);
        $timestamp = time() - ($daysAgo * 86400) - $secondsOffset;

        return gmdate('Y-m-d\\TH:i:s.000+00:00', $timestamp);
    }

    /**
     * @return array{code: string, label: string, color: string|null}
     */
    private function pickStatusDefinition(string $seed, int $offset = 0): array
    {
        $index = $this->randomInt($seed, 0, count(self::STATUS_DEFINITIONS) - 1, $offset);

        return self::STATUS_DEFINITIONS[$index] ?? self::STATUS_DEFINITIONS[0];
    }

    private function slugify(string $value): string
    {
        $normalized = $this->toLowercase($value);
        $normalized = str_replace(
            ['ä', 'ö', 'ü', 'ß'],
            ['ae', 'oe', 'ue', 'ss'],
            $normalized
        );
        $normalized = preg_replace('/[^a-z0-9]+/u', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }

    private function toLowercase(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value);
        }

        return strtolower($value);
    }
}
