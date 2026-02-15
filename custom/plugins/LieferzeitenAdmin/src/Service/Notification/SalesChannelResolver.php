<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Notification;

use Doctrine\DBAL\Connection;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SalesChannelResolver
{
    private const CONFIG_KEY = 'LieferzeitenAdmin.config.notificationSalesChannelMapping';

    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function resolve(?string $sourceSystem, ?string $externalOrderId, ?string $paketNumber = null, ?string $fallbackSalesChannelId = null): ?string
    {
        $normalizedFallback = $this->normalizeSalesChannelId($fallbackSalesChannelId);
        if ($normalizedFallback !== null) {
            return $normalizedFallback;
        }

        $normalizedExternalOrderId = $this->normalizeString($externalOrderId);
        if ($normalizedExternalOrderId !== null) {
            $salesChannelId = $this->findOrderSalesChannelIdByOrderNumber($normalizedExternalOrderId)
                ?? $this->findOrderSalesChannelIdByCustomField($normalizedExternalOrderId);
            if ($salesChannelId !== null) {
                return $salesChannelId;
            }
        }

        $normalizedSourceSystem = $this->normalizeString($sourceSystem);
        if ($normalizedSourceSystem !== null) {
            $salesChannelId = $this->resolveFromSourceMapping($normalizedSourceSystem);
            if ($salesChannelId !== null) {
                return $salesChannelId;
            }
        }

        return null;
    }

    private function findOrderSalesChannelIdByOrderNumber(string $externalOrderId): ?string
    {
        try {
            $salesChannelId = $this->connection->fetchOne(
                'SELECT LOWER(HEX(sales_channel_id))
                 FROM `order`
                 WHERE order_number = :orderNumber
                 ORDER BY created_at DESC
                 LIMIT 1',
                ['orderNumber' => $externalOrderId],
            );
        } catch (\Throwable) {
            return null;
        }

        return $this->normalizeSalesChannelId($salesChannelId);
    }

    private function findOrderSalesChannelIdByCustomField(string $externalOrderId): ?string
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT LOWER(HEX(sales_channel_id)) AS sales_channel_id, custom_fields
                 FROM `order`
                 ORDER BY created_at DESC
                 LIMIT 250'
            );
        } catch (\Throwable) {
            return null;
        }

        foreach ($rows as $row) {
            $customFields = $this->decodeCustomFields($row['custom_fields'] ?? null);
            if ($customFields === null) {
                continue;
            }

            $candidates = [
                $customFields['externalOrderId'] ?? null,
                $customFields['external_order_id'] ?? null,
                $customFields['orderNumberExternal'] ?? null,
                $customFields['order_number_external'] ?? null,
            ];

            foreach ($candidates as $candidate) {
                if (is_string($candidate) && trim($candidate) === $externalOrderId) {
                    return $this->normalizeSalesChannelId($row['sales_channel_id'] ?? null);
                }
            }
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    private function decodeCustomFields(mixed $raw): ?array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function resolveFromSourceMapping(string $sourceSystem): ?string
    {
        $rawMapping = $this->systemConfigService->get(self::CONFIG_KEY);
        if (!is_array($rawMapping) && !is_string($rawMapping)) {
            return null;
        }

        if (is_string($rawMapping)) {
            try {
                $rawMapping = json_decode($rawMapping, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                return null;
            }
        }

        if (!is_array($rawMapping)) {
            return null;
        }

        $normalizedKey = mb_strtolower(trim($sourceSystem));
        $candidate = $rawMapping[$normalizedKey] ?? null;

        return $this->normalizeSalesChannelId($candidate);
    }

    private function normalizeSalesChannelId(mixed $salesChannelId): ?string
    {
        if (!is_string($salesChannelId)) {
            return null;
        }

        $normalized = strtolower(trim($salesChannelId));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeString(?string $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
