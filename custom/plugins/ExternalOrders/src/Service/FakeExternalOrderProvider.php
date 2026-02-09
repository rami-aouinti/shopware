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

    private const STATUS_LABELS = [
        'processing' => 'In Bearbeitung',
        'shipped' => 'Versendet',
        'closed' => 'Abgeschlossen',
        'cancelled' => 'Storniert',
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
                $status = $this->pickValue(array_keys(self::STATUS_LABELS), $id, 3);
                $statusLabel = self::STATUS_LABELS[$status] ?? 'In Bearbeitung';
                $totalItems = $this->randomInt($id, 1, 6, 4);
                $pricePerItem = $this->randomInt($id, 2500, 19500, 5) / 100;
                $totalRevenue = round($totalItems * $pricePerItem, 2);

                $orders[] = [
                    'id' => $id,
                    'channel' => $channel,
                    'orderNumber' => $orderNumber,
                    'customerName' => $customerName,
                    'orderReference' => $orderReference,
                    'email' => $email,
                    'date' => $this->randomOrderDate($id),
                    'status' => $status,
                    'statusLabel' => $statusLabel,
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
                'name' => sprintf('Medizinisches Produkt %d', $index),
                'quantity' => $quantity,
                'netPrice' => $netPrice,
                'taxRate' => $taxRate,
                'grossPrice' => $grossPrice,
                'totalPrice' => $lineTotal,
            ];
        }

        $shippingTotal = $this->randomInt($id, 490, 1290, 30) / 100;
        $sumTotal = round($itemsTotal + $shippingTotal, 2);
        $orderNumber = $order['orderNumber'] ?? $id;
        $orderDate = $order['date'] ?? $this->randomOrderDate($id);
        $statusLabel = $order['statusLabel'] ?? 'In Bearbeitung';

        return [
            'orderNumber' => $orderNumber,
            'customer' => [
                'number' => sprintf('K-%s', strtoupper(substr($id, -6))),
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $order['email']
                    ?? sprintf('%s.%s@example.com', $this->slugify($firstName), $this->slugify($lastName)),
                'group' => $this->pickValue(['B2B', 'Retail', 'Premium'], $id, 11),
            ],
            'billingAddress' => [
                'company' => sprintf('%s Medizintechnik', $lastName),
                'street' => sprintf('%s %d', $street, $streetNumber),
                'zip' => $zip,
                'city' => $city,
                'country' => $country,
            ],
            'shippingAddress' => [
                'name' => sprintf('%s %s', $firstName, $lastName),
                'street' => sprintf('%s %d', $street, $streetNumber),
                'zipCity' => sprintf('%s %s', $zip, $city),
                'country' => $country,
            ],
            'payment' => [
                'method' => $paymentMethod,
                'code' => strtolower($this->slugify($paymentMethod)),
                'dueDate' => $this->randomOrderDate($id, 14),
                'outstanding' => '0,00 €',
                'settled' => 'Bezahlt',
                'extra' => 'Transaktion bestätigt',
            ],
            'shipping' => [
                'method' => 'DHL',
                'carrier' => 'DHL',
                'trackingNumbers' => [
                    sprintf('00340434%s', $this->randomInt($id, 100000, 999999, 12)),
                ],
            ],
            'additional' => [
                'orderDate' => $orderDate,
                'status' => $statusLabel,
                'orderType' => $this->pickValue(['Standard', 'Express', 'Premium'], $id, 13),
                'notes' => $this->pickValue([
                    'Bitte vor 12 Uhr liefern.',
                    'Lieferung bei Nachbarn möglich.',
                    'Bitte telefonisch avisieren.',
                    'Keine besonderen Hinweise.',
                ], $id, 14),
                'consultant' => sprintf('%s %s', substr($firstName, 0, 1), $lastName),
                'tenant' => strtoupper((string) ($order['channel'] ?? 'External')),
                'san6OrderNumber' => sprintf('SAN6-%s', strtoupper(substr($id, -6))),
                'orgaEntries' => [
                    sprintf('ORG-%d', $this->randomInt($id, 100, 999, 15)),
                ],
                'documents' => [
                    $this->pickValue(['Rechnung', 'Lieferschein', 'Auftrag', 'Storno'], $id, 16),
                ],
                'pdmsId' => sprintf('PDMS-%d', $this->randomInt($id, 1000, 9999, 17)),
                'pdmsVariant' => $this->pickValue(['V1', 'V2'], $id, 18),
                'topmArticleNumber' => sprintf('TM-%d', $this->randomInt($id, 1000, 9999, 19)),
                'topmExecution' => $this->pickValue(['Standard', 'Premium'], $id, 20),
                'statusHistorySource' => 'Faker',
            ],
            'items' => $items,
            'statusHistory' => [
                [
                    'status' => $statusLabel,
                    'date' => $orderDate,
                    'comment' => 'Auftrag angelegt',
                ],
            ],
            'totals' => [
                'items' => round($itemsTotal, 2),
                'shipping' => $shippingTotal,
                'sum' => $sumTotal,
                'tax' => round($taxTotal, 2),
                'net' => round($sumTotal - $taxTotal, 2),
            ],
        ];
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

    private function slugify(string $value): string
    {
        $normalized = mb_strtolower($value);
        $normalized = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $normalized);
        $normalized = preg_replace('/[^a-z0-9]+/u', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }
}
