<?php declare(strict_types=1);

namespace ExternalOrders\Service;

class FakeExternalOrderProvider
{
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
                $id = sprintf('fake-%s-%03d', $channel, $index);
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
        $firstName = $this->pickValue(self::FIRST_NAMES, $id, 1);
        $lastName = $this->pickValue(self::LAST_NAMES, $id, 2);
        $city = $this->pickValue(self::CITIES, $id, 3);
        $country = $this->pickValue(self::COUNTRIES, $id, 4);
        $street = $this->pickValue(self::STREETS, $id, 5);
        $streetNumber = $this->randomInt($id, 1, 42, 6);
        $zip = str_pad((string) $this->randomInt($id, 10000, 99999, 7), 5, '0', STR_PAD_LEFT);
        $paymentMethod = $this->pickValue(self::PAYMENT_METHODS, $id, 8);
        $items = [];
        $itemCount = $this->randomInt($id, 1, 4, 9);
        $itemsTotal = 0.0;
        $taxTotal = 0.0;

        for ($index = 1; $index <= $itemCount; $index += 1) {
            $quantity = $this->randomInt($id, 1, 3, 10 + $index);
            $netPrice = $this->randomInt($id, 1500, 9000, 20 + $index) / 100;
            $taxRate = 19;
            $grossPrice = round($netPrice * (1 + ($taxRate / 100)), 2);
            $lineTotal = round($grossPrice * $quantity, 2);
            $itemsTotal += $lineTotal;
            $taxTotal += round(($grossPrice - $netPrice) * $quantity, 2);

            $items[] = [
                'productName' => sprintf('Medizinisches Produkt %d', $index),
                'quantity' => $quantity,
                'finalPrice' => $lineTotal,
                'productPrice' => $netPrice,
                'productTax' => round(($grossPrice - $netPrice) * $quantity, 2),
            ];
        }

        $shippingTotal = $this->randomInt($id, 490, 1290, 30) / 100;
        $sumTotal = round($itemsTotal + $shippingTotal, 2);
        $orderDateIso = $order['datePurchased'] ?? $this->randomOrderDateIso8601($id);
        $statusLabel = $order['statusLabel'] ?? $order['ordersStatusName'] ?? 'Bezahlt / in Bearbeitung';
        $statusColor = $order['orderStatusColor'] ?? $this->resolveStatusColorByLabel($statusLabel);
        $customerName = trim(sprintf('%s %s', $firstName, $lastName));
        $countryIsoCode2 = $country === 'Österreich' ? 'AT' : ($country === 'Schweiz' ? 'CH' : 'DE');
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
            'paymentMethod' => $this->slugify($paymentMethod),
            'orderStatus' => $statusLabel,
            'orderStatusColor' => $statusColor,
            'shippingMethod' => $this->slugify($this->pickValue(['DHL', 'DPD', 'ebay'], $id, 36)),
            'auftragNumber' => (int) ($order['auftragNumber'] ?? 0),
            'items' => $items,
            'totals' => [
                ['title' => 'Warenwert:', 'value' => number_format($itemsTotal, 4, '.', ''), 'text' => $this->formatEuro($itemsTotal)],
                ['title' => 'Versandkosten:', 'value' => number_format($shippingTotal, 4, '.', ''), 'text' => $this->formatEuro($shippingTotal)],
                ['title' => '<b>Summe</b>:', 'value' => number_format($sumTotal, 4, '.', ''), 'text' => $this->formatEuro($sumTotal)],
                ['title' => 'inkl. 19% MwSt.:', 'value' => number_format($taxTotal, 4, '.', ''), 'text' => $this->formatEuro($taxTotal)],
                ['title' => 'Summe netto:', 'value' => number_format($sumTotal - $taxTotal, 4, '.', ''), 'text' => $this->formatEuro($sumTotal - $taxTotal)],
            ],
            'statusHistory' => $statusHistory,
            'customer' => [
                'id' => $this->randomInt($id, 1000, 99999, 37),
                'cid' => 'N/A',
                'vatId' => 'N/A',
                'status' => 12,
                'statusName' => 'Kundengruppe Ebay DE',
                'statusImage' => '',
                'statusDiscount' => 0.00,
                'name' => $customerName,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'gender' => '',
                'company' => '',
                'streetAddress' => sprintf('%s %d', $street, $streetNumber),
                'houseNumber' => '',
                'additionalInfo' => '',
                'suburb' => 'N/A',
                'city' => $city,
                'postcode' => $zip,
                'state' => '',
                'country' => $country,
                'telephone' => '',
                'emailAddress' => $order['email']
                    ?? sprintf('%s.%s@example.com', $this->slugify($firstName), $this->slugify($lastName)),
                'addressFormatId' => 5,
            ],
            'billing' => [
                'name' => $customerName,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'gender' => '',
                'company' => '',
                'streetAddress' => sprintf('%s %d', $street, $streetNumber),
                'houseNumber' => '',
                'additionalInfo' => '',
                'suburb' => 'N/A',
                'city' => $city,
                'postcode' => $zip,
                'state' => '',
                'country' => $country,
                'countryIsoCode2' => $countryIsoCode2,
                'addressFormatId' => 5,
            ],
            'delivery' => [
                'name' => $customerName,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'gender' => '',
                'company' => '',
                'streetAddress' => sprintf('%s %d', $street, $streetNumber),
                'houseNumber' => '',
                'additionalInfo' => '',
                'suburb' => 'N/A',
                'city' => $city,
                'postcode' => $zip,
                'state' => '',
                'country' => $country,
                'countryIsoCode2' => $countryIsoCode2,
                'addressFormatId' => 5,
            ],
        ];
    }

    private function formatEuro(float $value): string
    {
        return number_format($value, 2, ',', '.') . ' EUR';
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
