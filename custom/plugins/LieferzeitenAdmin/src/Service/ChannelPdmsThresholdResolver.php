<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ChannelPdmsThresholdResolver
{
    /**
     * @var list<string>
     */
    private const ORDER_LOOKUP_STRATEGY_ORDER = [
        'order_number',
        'order_custom_field_external_id',
        'lieferzeiten_paket_external_id_join',
        'lieferzeiten_paket_number_join',
    ];

    /**
     * @var list<string>
     */
    private const ORDER_EXTERNAL_ID_FIELD_CANDIDATES = [
        'external_order_id',
        'externalOrderId',
        'order_reference',
        'orderReference',
        'integration_order_id',
        'integrationOrderId',
        'source_order_id',
        'sourceOrderId',
    ];

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
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    private readonly LoggerInterface $logger;

    /**
     * @return array{shipping:array{workingDays:int,cutoff:string},delivery:array{workingDays:int,cutoff:string}}
     */
    public function resolveForOrder(string $sourceSystem, ?string $externalOrderId, ?string $positionNumber = null): array
    {
        $defaults = $this->channelDateSettingsProvider->getForChannel($sourceSystem);

        if (!is_string($externalOrderId) || trim($externalOrderId) === '') {
            $this->logger->warning('PDMS threshold resolution fell back to channel defaults: missing external order id.', [
                'sourceSystem' => $sourceSystem,
                'externalOrderId' => $externalOrderId,
                'reason' => 'missing_external_order_id',
            ]);

            return $defaults;
        }

        $orderContext = $this->resolveOrderContext($sourceSystem, $externalOrderId);
        $salesChannelId = $orderContext['salesChannelId'];
        $pdmsSlotRaw = $this->resolvePdmsSlotRaw($orderContext['orderId'], $orderContext['orderCustomFields'], $positionNumber);
        $pdmsLieferzeit = $this->resolvePdmsLieferzeitKey($salesChannelId, $pdmsSlotRaw);

        if ($salesChannelId === null || $pdmsLieferzeit === null) {
            $this->logger->warning('PDMS threshold resolution fell back to channel defaults.', [
                'sourceSystem' => $sourceSystem,
                'externalOrderId' => $externalOrderId,
                'orderLookupStrategy' => $orderContext['strategy'],
                'orderLookupAttempts' => $orderContext['lookupAttempts'],
                'reason' => $salesChannelId === null ? 'sales_channel_not_resolved' : 'pdms_slot_or_mapping_not_resolved',
            ]);

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

    /**
     * @return array{orderId:?string,salesChannelId:?string,orderCustomFields:?array<string,mixed>,strategy:string,lookupAttempts:list<array{strategy:string,matched:bool}>}
     */
    private function resolveOrderContext(string $sourceSystem, string $externalOrderId): array
    {
        $externalOrderId = trim($externalOrderId);
        $lookupAttempts = [];

        $primaryMatch = $this->fetchOrderByOrderNumber($externalOrderId);
        $lookupAttempts[] = ['strategy' => 'order_number', 'matched' => $primaryMatch !== null];
        if ($primaryMatch !== null) {
            return [
                'orderId' => $primaryMatch['id'],
                'salesChannelId' => $primaryMatch['sales_channel_id'],
                'orderCustomFields' => $primaryMatch['custom_fields'],
                'strategy' => 'order_number',
                'lookupAttempts' => $lookupAttempts,
            ];
        }

        $customFieldMatch = $this->fetchOrderByExternalIdCustomField($externalOrderId);
        $lookupAttempts[] = ['strategy' => 'order_custom_field_external_id', 'matched' => $customFieldMatch !== null];
        if ($customFieldMatch !== null) {
            $this->logger->info('PDMS threshold resolver order lookup used fallback strategy.', [
                'externalOrderId' => $externalOrderId,
                'sourceSystem' => $sourceSystem,
                'strategy' => 'order_custom_field_external_id',
                'lookupStrategyOrder' => self::ORDER_LOOKUP_STRATEGY_ORDER,
                'lookupAttempts' => $lookupAttempts,
            ]);

            return [
                'orderId' => $customFieldMatch['id'],
                'salesChannelId' => $customFieldMatch['sales_channel_id'],
                'orderCustomFields' => $customFieldMatch['custom_fields'],
                'strategy' => 'order_custom_field_external_id',
                'lookupAttempts' => $lookupAttempts,
            ];
        }

        $paketJoinMatch = $this->fetchOrderViaPaketExternalId($sourceSystem, $externalOrderId);
        $lookupAttempts[] = ['strategy' => 'lieferzeiten_paket_external_id_join', 'matched' => $paketJoinMatch !== null];
        if ($paketJoinMatch !== null) {
            $this->logger->info('PDMS threshold resolver order lookup used fallback strategy.', [
                'externalOrderId' => $externalOrderId,
                'sourceSystem' => $sourceSystem,
                'strategy' => 'lieferzeiten_paket_external_id_join',
                'lookupStrategyOrder' => self::ORDER_LOOKUP_STRATEGY_ORDER,
                'lookupAttempts' => $lookupAttempts,
            ]);

            return [
                'orderId' => $paketJoinMatch['id'],
                'salesChannelId' => $paketJoinMatch['sales_channel_id'],
                'orderCustomFields' => $paketJoinMatch['custom_fields'],
                'strategy' => 'lieferzeiten_paket_external_id_join',
                'lookupAttempts' => $lookupAttempts,
            ];
        }

        $paketNumberJoinMatch = $this->fetchOrderViaPaketNumber($sourceSystem, $externalOrderId);
        $lookupAttempts[] = ['strategy' => 'lieferzeiten_paket_number_join', 'matched' => $paketNumberJoinMatch !== null];
        if ($paketNumberJoinMatch !== null) {
            $this->logger->info('PDMS threshold resolver order lookup used fallback strategy.', [
                'externalOrderId' => $externalOrderId,
                'sourceSystem' => $sourceSystem,
                'strategy' => 'lieferzeiten_paket_number_join',
                'lookupStrategyOrder' => self::ORDER_LOOKUP_STRATEGY_ORDER,
                'lookupAttempts' => $lookupAttempts,
            ]);

            return [
                'orderId' => $paketNumberJoinMatch['id'],
                'salesChannelId' => $paketNumberJoinMatch['sales_channel_id'],
                'orderCustomFields' => $paketNumberJoinMatch['custom_fields'],
                'strategy' => 'lieferzeiten_paket_number_join',
                'lookupAttempts' => $lookupAttempts,
            ];
        }

        return [
            'orderId' => null,
            'salesChannelId' => null,
            'orderCustomFields' => null,
            'strategy' => 'not_found',
            'lookupAttempts' => $lookupAttempts,
        ];
    }

    /**
     * @return array{id:string,sales_channel_id:string,custom_fields:?array<string,mixed>}|null
     */
    private function fetchOrderByOrderNumber(string $orderNumber): ?array
    {
        try {
            $row = $this->connection->fetchAssociative(
                'SELECT id, sales_channel_id, custom_fields
                 FROM `order`
                 WHERE order_number = :orderNumber
                 ORDER BY created_at DESC
                 LIMIT 1',
                ['orderNumber' => $orderNumber],
            );
        } catch (\Throwable) {
            return null;
        }

        return $this->normalizeOrderRow($row);
    }

    /**
     * @return array{id:string,sales_channel_id:string,custom_fields:?array<string,mixed>}|null
     */
    private function fetchOrderByExternalIdCustomField(string $externalOrderId): ?array
    {
        try {
            $orderRows = $this->connection->fetchAllAssociative(
                'SELECT id, sales_channel_id, custom_fields
                 FROM `order`
                 ORDER BY created_at DESC
                 LIMIT 250'
            );
        } catch (\Throwable) {
            return null;
        }

        foreach ($orderRows as $orderRow) {
            $normalized = $this->normalizeOrderRow($orderRow);
            if ($normalized === null) {
                continue;
            }

            if ($this->externalOrderIdMatchesCustomFields($externalOrderId, $normalized['custom_fields'])) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @return array{id:string,sales_channel_id:string,custom_fields:?array<string,mixed>}|null
     */
    private function fetchOrderViaPaketExternalId(string $sourceSystem, string $externalOrderId): ?array
    {
        try {
            $paketRows = $this->connection->fetchAllAssociative(
                'SELECT paket_number, external_order_id
                 FROM `lieferzeiten_paket`
                 WHERE external_order_id = :externalOrderId
                   AND (source_system = :sourceSystem OR source_system IS NULL)
                 ORDER BY created_at DESC
                 LIMIT 25',
                [
                    'externalOrderId' => $externalOrderId,
                    'sourceSystem' => $sourceSystem,
                ],
            );
        } catch (\Throwable) {
            return null;
        }

        foreach ($paketRows as $paketRow) {
            $paketNumber = is_scalar($paketRow['paket_number'] ?? null) ? trim((string) $paketRow['paket_number']) : '';
            if ($paketNumber === '') {
                continue;
            }

            $orderByPaketNumber = $this->fetchOrderByOrderNumber($paketNumber);
            if ($orderByPaketNumber !== null) {
                return $orderByPaketNumber;
            }

            $orderByPaketRef = $this->fetchOrderByExternalIdCustomField($paketNumber);
            if ($orderByPaketRef !== null) {
                return $orderByPaketRef;
            }
        }

        return null;
    }

    /**
     * @return array{id:string,sales_channel_id:string,custom_fields:?array<string,mixed>}|null
     */
    private function fetchOrderViaPaketNumber(string $sourceSystem, string $externalOrderId): ?array
    {
        try {
            $paketRows = $this->connection->fetchAllAssociative(
                'SELECT paket_number
                 FROM `lieferzeiten_paket`
                 WHERE paket_number = :paketNumber
                   AND (source_system = :sourceSystem OR source_system IS NULL)
                 ORDER BY created_at DESC
                 LIMIT 25',
                [
                    'paketNumber' => $externalOrderId,
                    'sourceSystem' => $sourceSystem,
                ],
            );
        } catch (\Throwable) {
            return null;
        }

        foreach ($paketRows as $paketRow) {
            $paketNumber = is_scalar($paketRow['paket_number'] ?? null) ? trim((string) $paketRow['paket_number']) : '';
            if ($paketNumber === '') {
                continue;
            }

            $orderByPaketNumber = $this->fetchOrderByOrderNumber($paketNumber);
            if ($orderByPaketNumber !== null) {
                return $orderByPaketNumber;
            }

            $orderByPaketRef = $this->fetchOrderByExternalIdCustomField($paketNumber);
            if ($orderByPaketRef !== null) {
                return $orderByPaketRef;
            }
        }

        return null;
    }

    private function resolvePdmsSlotRaw(?string $orderId, ?array $orderCustomFields, ?string $positionNumber): mixed
    {
        if (!is_string($orderId) || $orderId === '') {
            return null;
        }

        if (is_string($positionNumber) && trim($positionNumber) !== '') {
            $matchedLineItemSlot = $this->resolveLineItemPdmsSlot($orderId, $positionNumber);
            if ($matchedLineItemSlot !== null) {
                return $matchedLineItemSlot;
            }
        }

        return $this->findFirstValue($orderCustomFields, self::PDMS_SLOT_FIELD_CANDIDATES);
    }

    /**
     * @param array<string,mixed>|null $customFields
     */
    private function externalOrderIdMatchesCustomFields(string $externalOrderId, ?array $customFields): bool
    {
        if ($customFields === null) {
            return false;
        }

        $expected = trim($externalOrderId);
        if ($expected === '') {
            return false;
        }

        foreach (self::ORDER_EXTERNAL_ID_FIELD_CANDIDATES as $field) {
            foreach ($this->findValuesForField($customFields, $field) as $value) {
                if (trim($value) === $expected) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function findValuesForField(array $payload, string $field): array
    {
        $values = [];

        foreach ($payload as $key => $value) {
            if ((string) $key === $field && is_scalar($value)) {
                $values[] = (string) $value;
            }

            if (is_array($value)) {
                foreach ($this->findValuesForField($value, $field) as $nestedValue) {
                    $values[] = $nestedValue;
                }
            }
        }

        return $values;
    }

    /**
     * @return array{id:string,sales_channel_id:string,custom_fields:?array<string,mixed>}|null
     */
    private function normalizeOrderRow(mixed $row): ?array
    {
        if (!is_array($row)) {
            return null;
        }

        $id = is_string($row['id'] ?? null) ? $row['id'] : '';
        $salesChannelId = is_string($row['sales_channel_id'] ?? null) ? $row['sales_channel_id'] : '';
        if ($id === '' || $salesChannelId === '') {
            return null;
        }

        return [
            'id' => $id,
            'sales_channel_id' => $salesChannelId,
            'custom_fields' => $this->decodeJsonObject($row['custom_fields'] ?? null),
        ];
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
