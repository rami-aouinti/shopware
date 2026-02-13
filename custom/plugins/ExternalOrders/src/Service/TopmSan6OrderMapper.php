<?php declare(strict_types=1);

namespace ExternalOrders\Service;

class TopmSan6OrderMapper
{
    private const INLINE_ATTACHMENT_LIMIT_BYTES = 262144;

    /**
     * @param array<string, mixed> $orderData
     *
     * @return array<string, mixed>
     */
    public function mapOrder(array $orderData): array
    {
        $trimmed = $this->trimStrings($orderData);

        $orderNumber = $this->pickFirstString($trimmed, ['Auftragsnummer', 'auftragsnummer', 'orderNumber', 'Belegnummer']);
        $externalId = $this->pickFirstString($trimmed, ['externalId', 'id', 'Auftragsnummer', 'auftragsnummer', 'orderNumber']) ?? $orderNumber ?? '';

        $customerNumber = $this->pickFirstString($trimmed, ['Kundennummer', 'kundennummer']);
        $customerName = $this->pickFirstString($trimmed, ['Kundenname', 'kundenname', 'Name', 'name']) ?? 'N/A';
        $email = $this->pickFirstString($trimmed, ['E-Mail', 'email', 'Mailadresse']) ?? 'N/A';

        $orderDate = $this->normalizeDate($this->pickFirstString($trimmed, ['Auftragsdatum', 'Datum', 'datePurchased', 'Bestelldatum']));
        $deliveryDate = $this->normalizeDate($this->pickFirstString($trimmed, ['Lieferdatum', 'Versanddatum']));
        $status = $this->pickFirstString($trimmed, ['Status', 'status']) ?? 'processing';

        $items = $this->mapPositions($trimmed);
        $totals = $this->mapTotals($trimmed, $items);
        $attachments = $this->mapAttachments($trimmed);

        $mapped = [
            'externalId' => $externalId,
            'orderNumber' => $orderNumber ?? $externalId,
            'auftragNumber' => $orderNumber ?? $externalId,
            'orderReference' => $orderNumber ?? $externalId,
            'customerName' => $customerName,
            'customersName' => $customerName,
            'customersEmailAddress' => $email,
            'datePurchased' => $orderDate ?? '',
            'date' => $orderDate ?? '',
            'ordersStatusName' => ucfirst($status),
            'status' => strtolower($status),
            'totalItems' => count($items),
            'totalRevenue' => $totals['sum'],
            'channel' => 'san6',
            'detail' => [
                'orderNumber' => $orderNumber ?? $externalId,
                'customer' => [
                    'customerNumber' => $customerNumber,
                    'firstName' => $customerName,
                    'lastName' => '',
                    'email' => $email,
                ],
                'items' => $items,
                'totals' => $totals,
                'additional' => [
                    'status' => ucfirst($status),
                    'orderDate' => $orderDate ?? '',
                    'deliveryDate' => $deliveryDate ?? '',
                    'attachments' => $attachments['inline'],
                ],
            ],
        ];

        if ($attachments['separate'] !== []) {
            $mapped['attachmentPayload'] = $attachments['separate'];
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $source
     * @param array<int, string> $keys
     */
    private function pickFirstString(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }

            $value = $source[$key];
            if (!is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private function mapPositions(array $data): array
    {
        $positions = $this->findPositionsNode($data);
        $items = [];

        foreach ($positions as $position) {
            if (!is_array($position)) {
                continue;
            }

            $skuRaw = $this->pickFirstString($position, ['Artikel', 'Artikelnummer', 'Referenz', 'article', 'sku']) ?? '';
            $normalizedReference = $this->normalizeArticleReference($skuRaw);
            $quantity = (int) ($this->pickFirstString($position, ['Menge', 'menge', 'Anzahl']) ?? 1);
            $price = (float) str_replace(',', '.', (string) ($this->pickFirstString($position, ['Preis', 'Einzelpreis']) ?? '0'));

            $items[] = [
                'productNumber' => $normalizedReference['normalized'],
                'sku' => $normalizedReference['reference'],
                'variant' => $normalizedReference['variant'],
                'name' => $this->pickFirstString($position, ['Bezeichnung', 'Name']) ?? $normalizedReference['reference'],
                'quantity' => $quantity,
                'price' => $price,
                'total' => (float) ($quantity * $price),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, mixed>
     */
    private function findPositionsNode(array $data): array
    {
        foreach (['Positionen', 'positionen', 'Positions', 'positions', 'Position'] as $key) {
            if (!isset($data[$key])) {
                continue;
            }

            $value = $data[$key];
            if (!is_array($value)) {
                continue;
            }

            if (array_is_list($value)) {
                $positions = [];

                foreach ($value as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }

                    if (isset($entry['Position']) && is_array($entry['Position'])) {
                        $nested = $entry['Position'];
                        $positions = [...$positions, ...(array_is_list($nested) ? $nested : [$nested])];

                        continue;
                    }

                    $positions[] = $entry;
                }

                if ($positions !== []) {
                    return $positions;
                }
            }

            if (isset($value['Position']) && is_array($value['Position'])) {
                $nested = $value['Position'];

                return array_is_list($nested) ? $nested : [$nested];
            }

            return [$value];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<string, float>
     */
    private function mapTotals(array $data, array $items): array
    {
        $sum = $this->pickFirstString($data, ['Gesamtbetrag', 'Betrag', 'totalRevenue', 'Summe']);
        if ($sum === null) {
            $sum = (string) array_sum(array_map(static fn (array $item): float => (float) ($item['total'] ?? 0.0), $items));
        }

        return ['sum' => (float) str_replace(',', '.', $sum)];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{inline: array<int, array<string, string>>, separate: array<int, array<string, string|int>>}
     */
    private function mapAttachments(array $data): array
    {
        $node = $data['Anlagen'] ?? $data['attachments'] ?? null;
        if (!is_array($node)) {
            return ['inline' => [], 'separate' => []];
        }

        $attachments = array_is_list($node) ? $node : [$node];
        $inline = [];
        $separate = [];

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $content = $this->pickFirstString($attachment, ['InhaltBase64', 'Datei', 'base64', 'content']);
            if ($content === null) {
                continue;
            }

            $name = $this->pickFirstString($attachment, ['Dateiname', 'filename', 'name']) ?? 'attachment';
            $size = strlen((string) base64_decode($content, true));

            if ($size <= self::INLINE_ATTACHMENT_LIMIT_BYTES) {
                $inline[] = ['name' => $name, 'content' => $content];
                continue;
            }

            $separate[] = ['name' => $name, 'content' => $content, 'size' => $size];
        }

        return ['inline' => $inline, 'separate' => $separate];
    }

    /**
     * @return array{reference: string, variant: string, normalized: string}
     */
    private function normalizeArticleReference(string $reference): array
    {
        $cleanReference = trim(preg_replace('/\s+/u', ' ', $reference) ?? $reference);

        if (preg_match('/^(.*?)[\.\s]+(\d{1,2})$/u', $cleanReference, $matches) === 1) {
            $base = trim($matches[1]);
            $variant = str_pad($matches[2], 2, '0', STR_PAD_LEFT);

            return [
                'reference' => $base,
                'variant' => $variant,
                'normalized' => sprintf('%s.%s', $base, $variant),
            ];
        }

        return [
            'reference' => $cleanReference,
            'variant' => '00',
            'normalized' => sprintf('%s.%s', $cleanReference, '00'),
        ];
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function trimStrings(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->trimStrings($item);
        }

        return $value;
    }

    private function normalizeDate(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        foreach (['d.m.Y', 'd/m/Y', 'Y-m-d', 'Ymd'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $date);
            if ($parsed instanceof \DateTimeImmutable) {
                return $parsed->format('Y-m-d');
            }
        }

        return $date;
    }
}
