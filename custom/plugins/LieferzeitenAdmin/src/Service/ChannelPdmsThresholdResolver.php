<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Doctrine\DBAL\Connection;

class ChannelPdmsThresholdResolver
{
    /**
     * @var list<string>
     */
    private const PDMS_SLOT_FIELD_CANDIDATES = [
        'pdms_slot',
        'pdmsSlot',
        'lieferzeit_slot',
        'lieferzeitSlot',
        'pdms_lieferzeit',
        'pdmsLieferzeit',
        'lieferzeit',
    ];

    /**
     * @var list<string>
     */
    private const POSITION_NUMBER_FIELD_CANDIDATES = [
        'position_number',
        'positionNumber',
        'line_item_number',
        'lineItemNumber',
        'san6_pos',
        'san6Pos',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly ChannelDateSettingsProvider $channelDateSettingsProvider,
    ) {
    }

    /**
     * @return array{shipping:array{workingDays:int,cutoff:string},delivery:array{workingDays:int,cutoff:string}}
     */
    public function resolveForOrder(string $sourceSystem, ?string $externalOrderId, ?string $positionNumber = null): array
    {
        $defaults = $this->channelDateSettingsProvider->getForChannel($sourceSystem);

        if (!is_string($externalOrderId) || trim($externalOrderId) === '') {
            return $defaults;
        }

        $salesChannelId = $this->resolveSalesChannelId($externalOrderId);
        $pdmsSlotRaw = $this->resolvePdmsSlotRaw($externalOrderId, $positionNumber);
        $pdmsLieferzeit = $this->resolvePdmsLieferzeitKey($salesChannelId, $pdmsSlotRaw);

        if ($salesChannelId === null || $pdmsLieferzeit === null) {
            return $defaults;
        }

        return $this->resolveForSalesChannelAndPdmsLieferzeit($sourceSystem, $salesChannelId, $pdmsLieferzeit);
    }

    /**
     * Explicit fallback behavior:
     * - If no LMS threshold entry exists for the resolved sales-channel + PDMS value, channel defaults are returned.
     * - If lookup fails (query/DB issue), channel defaults are returned.
     *
     * @return array{shipping:array{workingDays:int,cutoff:string},delivery:array{workingDays:int,cutoff:string}}
     */
    public function resolveForSalesChannelAndPdmsLieferzeit(string $sourceSystem, string $salesChannelId, string $pdmsLieferzeit): array
    {
        $defaults = $this->channelDateSettingsProvider->getForChannel($sourceSystem);

        if (trim($salesChannelId) === '' || trim($pdmsLieferzeit) === '') {
            return $defaults;
        }

        try {
            $row = $this->connection->fetchAssociative(
                'SELECT shipping_overdue_working_days, delivery_overdue_working_days
                 FROM `lieferzeiten_channel_pdms_threshold`
                 WHERE sales_channel_id = :salesChannelId
                   AND pdms_lieferzeit = :pdmsLieferzeit
                 LIMIT 1',
                [
                    'salesChannelId' => $salesChannelId,
                    'pdmsLieferzeit' => $pdmsLieferzeit,
                ],
            );
        } catch (\Throwable) {
            return $defaults;
        }

        if (!is_array($row) || $row === []) {
            return $defaults;
        }

        return [
            'shipping' => [
                'workingDays' => max(0, (int) ($row['shipping_overdue_working_days'] ?? $defaults['shipping']['workingDays'])),
                'cutoff' => $defaults['shipping']['cutoff'],
            ],
            'delivery' => [
                'workingDays' => max(0, (int) ($row['delivery_overdue_working_days'] ?? $defaults['delivery']['workingDays'])),
                'cutoff' => $defaults['delivery']['cutoff'],
            ],
        ];
    }

    private function resolveSalesChannelId(string $externalOrderId): ?string
    {
        try {
            $value = $this->connection->fetchOne(
                'SELECT sales_channel_id
                 FROM `order`
                 WHERE order_number = :orderNumber
                 ORDER BY created_at DESC
                 LIMIT 1',
                ['orderNumber' => $externalOrderId],
            );
        } catch (\Throwable) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function resolvePdmsSlotRaw(string $externalOrderId, ?string $positionNumber): mixed
    {
        try {
            $orderRow = $this->connection->fetchAssociative(
                'SELECT id, custom_fields
                 FROM `order`
                 WHERE order_number = :orderNumber
                 ORDER BY created_at DESC
                 LIMIT 1',
                ['orderNumber' => $externalOrderId],
            );
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($orderRow) || !is_string($orderRow['id'] ?? null) || $orderRow['id'] === '') {
            return null;
        }

        $orderId = $orderRow['id'];
        if (is_string($positionNumber) && trim($positionNumber) !== '') {
            $matchedLineItemSlot = $this->resolveLineItemPdmsSlot($orderId, $positionNumber);
            if ($matchedLineItemSlot !== null) {
                return $matchedLineItemSlot;
            }
        }

        $orderCustomFields = $this->decodeJsonObject($orderRow['custom_fields'] ?? null);

        return $this->findFirstValue($orderCustomFields, self::PDMS_SLOT_FIELD_CANDIDATES);
    }

    private function resolveLineItemPdmsSlot(string $orderId, string $positionNumber): mixed
    {
        try {
            $lineItemRows = $this->connection->fetchAllAssociative(
                'SELECT custom_fields
                 FROM `order_line_item`
                 WHERE order_id = :orderId
                 ORDER BY created_at ASC',
                ['orderId' => $orderId],
            );
        } catch (\Throwable) {
            return null;
        }

        $fallbackSlot = null;
        foreach ($lineItemRows as $lineItemRow) {
            $customFields = $this->decodeJsonObject($lineItemRow['custom_fields'] ?? null);
            if ($customFields === null) {
                continue;
            }

            $slotCandidate = $this->findFirstValue($customFields, self::PDMS_SLOT_FIELD_CANDIDATES);
            if ($slotCandidate === null) {
                continue;
            }

            if ($fallbackSlot === null) {
                $fallbackSlot = $slotCandidate;
            }

            if ($this->matchesPositionNumber($customFields, $positionNumber)) {
                return $slotCandidate;
            }
        }

        return $fallbackSlot;
    }

    private function resolvePdmsLieferzeitKey(?string $salesChannelId, mixed $pdmsSlotRaw): ?string
    {
        if (!is_string($salesChannelId) || $salesChannelId === '' || $pdmsSlotRaw === null) {
            return null;
        }

        try {
            $customFieldsRaw = $this->connection->fetchOne(
                'SELECT custom_fields FROM `sales_channel` WHERE id = :id LIMIT 1',
                ['id' => $salesChannelId],
            );
        } catch (\Throwable) {
            $customFieldsRaw = null;
        }
        $customFields = $this->decodeJsonObject($customFieldsRaw);
        $mapping = $this->decodeJsonObject($customFields['pdms_lieferzeiten_mapping'] ?? null);

        $slotValue = is_scalar($pdmsSlotRaw) ? trim((string) $pdmsSlotRaw) : '';
        if ($slotValue === '') {
            return null;
        }

        if (is_array($mapping)) {
            $mapped = $mapping[$slotValue] ?? $mapping['slot' . $slotValue] ?? null;
            if (is_scalar($mapped)) {
                $mappedValue = trim((string) $mapped);
                if ($mappedValue !== '') {
                    return $mappedValue;
                }
            }
        }

        return $slotValue;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decodeJsonObject(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function matchesPositionNumber(array $customFields, string $positionNumber): bool
    {
        $positionNumber = trim($positionNumber);
        if ($positionNumber === '') {
            return false;
        }

        foreach (self::POSITION_NUMBER_FIELD_CANDIDATES as $field) {
            $value = $customFields[$field] ?? null;
            if (!is_scalar($value)) {
                continue;
            }

            if (trim((string) $value) === $positionNumber) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $keys
     */
    private function findFirstValue(?array $payload, array $keys): mixed
    {
        if ($payload === null) {
            return null;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null && $payload[$key] !== '') {
                return $payload[$key];
            }
        }

        return null;
    }
}
