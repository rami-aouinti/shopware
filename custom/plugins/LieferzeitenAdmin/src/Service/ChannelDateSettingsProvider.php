<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ArrayParameterType;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ChannelDateSettingsProvider
{
    private const DEFAULT_WORKING_DAYS = 2;
    private const DEFAULT_CUTOFF = '12:00';
    private const DEFAULT_SHIPPING_WORKING_DAYS = 0;

    public function __construct(
        private readonly SystemConfigService $config,
        private readonly Connection $connection,
    )
    {
    }

    /**
     * @return array{
     *     shipping:array{workingDays:int,cutoff:string},
     *     delivery:array{workingDays:int,cutoff:string}
     * }
     */
    public function getForChannel(string $channel): array
    {
        $persisted = $this->loadPersistedChannelSettings($channel);
        if ($persisted !== null) {
            return $persisted;
        }

        $key = match (mb_strtolower($channel)) {
            'gambio' => 'LieferzeitenAdmin.config.gambioDateSettings',
            default => 'LieferzeitenAdmin.config.shopwareDateSettings',
        };

        $raw = $this->config->get($key);
        if (!is_string($raw) || trim($raw) === '') {
            return $this->defaultSettings();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $this->defaultSettings();
        }

        $legacySettings = $this->normalizeRuleSettings($decoded, self::DEFAULT_WORKING_DAYS, self::DEFAULT_CUTOFF);

        return [
            'shipping' => $this->normalizeRuleSettings(
                is_array($decoded['shipping'] ?? null) ? $decoded['shipping'] : [],
                self::DEFAULT_SHIPPING_WORKING_DAYS,
                $legacySettings['cutoff'],
            ),
            'delivery' => $this->normalizeRuleSettings(
                is_array($decoded['delivery'] ?? null) ? $decoded['delivery'] : $decoded,
                $legacySettings['workingDays'],
                $legacySettings['cutoff'],
            ),
        ];
    }

    /**
     * @return array{shipping:array{workingDays:int,cutoff:string},delivery:array{workingDays:int,cutoff:string}}|null
     */
    private function loadPersistedChannelSettings(string $channel): ?array
    {
        $normalized = trim(mb_strtolower($channel));
        if ($normalized === '') {
            return null;
        }

        $candidates = array_values(array_unique([
            $normalized,
            str_replace('_', '-', $normalized),
            str_replace('-', '_', $normalized),
        ]));

        $row = $this->connection->fetchAssociative(
            'SELECT shipping_working_days, shipping_cutoff, delivery_working_days, delivery_cutoff
             FROM `lieferzeiten_channel_settings`
             WHERE LOWER(sales_channel_id) IN (:channels)
             ORDER BY CASE WHEN LOWER(sales_channel_id) = :preferred THEN 0 ELSE 1 END
             LIMIT 1',
            [
                'channels' => $candidates,
                'preferred' => $normalized,
            ],
            [
                'channels' => ArrayParameterType::STRING,
            ],
        );

        if (!is_array($row) || $row === []) {
            return null;
        }

        return [
            'shipping' => $this->normalizeRuleSettings([
                'workingDays' => $row['shipping_working_days'] ?? null,
                'cutoff' => $row['shipping_cutoff'] ?? null,
            ], self::DEFAULT_SHIPPING_WORKING_DAYS, self::DEFAULT_CUTOFF),
            'delivery' => $this->normalizeRuleSettings([
                'workingDays' => $row['delivery_working_days'] ?? null,
                'cutoff' => $row['delivery_cutoff'] ?? null,
            ], self::DEFAULT_WORKING_DAYS, self::DEFAULT_CUTOFF),
        ];
    }

    /**
     * @param array<string,mixed> $settings
     *
     * @return array{workingDays:int,cutoff:string}
     */
    private function normalizeRuleSettings(array $settings, int $defaultWorkingDays, string $defaultCutoff): array
    {
        $workingDays = (int) ($settings['workingDays'] ?? $defaultWorkingDays);
        if ($workingDays < 0) {
            $workingDays = 0;
        }

        $cutoff = (string) ($settings['cutoff'] ?? $defaultCutoff);
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $cutoff) !== 1) {
            $cutoff = $defaultCutoff;
        }

        return ['workingDays' => $workingDays, 'cutoff' => $cutoff];
    }

    /**
     * @return array{
     *     shipping:array{workingDays:int,cutoff:string},
     *     delivery:array{workingDays:int,cutoff:string}
     * }
     */
    private function defaultSettings(): array
    {
        return [
            'shipping' => [
                'workingDays' => self::DEFAULT_SHIPPING_WORKING_DAYS,
                'cutoff' => self::DEFAULT_CUTOFF,
            ],
            'delivery' => [
                'workingDays' => self::DEFAULT_WORKING_DAYS,
                'cutoff' => self::DEFAULT_CUTOFF,
            ],
        ];
    }
}
