<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ChannelDateSettingsProvider
{
    private const DEFAULT_WORKING_DAYS = 2;
    private const DEFAULT_CUTOFF = '14:00';

    public function __construct(private readonly SystemConfigService $config)
    {
    }

    /**
     * @return array{workingDays:int,cutoff:string}
     */
    public function getForChannel(string $channel): array
    {
        $key = match (mb_strtolower($channel)) {
            'gambio' => 'LieferzeitenAdmin.config.gambioDateSettings',
            default => 'LieferzeitenAdmin.config.shopwareDateSettings',
        };

        $raw = $this->config->get($key);
        if (!is_string($raw) || trim($raw) === '') {
            return ['workingDays' => self::DEFAULT_WORKING_DAYS, 'cutoff' => self::DEFAULT_CUTOFF];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['workingDays' => self::DEFAULT_WORKING_DAYS, 'cutoff' => self::DEFAULT_CUTOFF];
        }

        $workingDays = (int) ($decoded['workingDays'] ?? self::DEFAULT_WORKING_DAYS);
        if ($workingDays < 0) {
            $workingDays = 0;
        }

        $cutoff = (string) ($decoded['cutoff'] ?? self::DEFAULT_CUTOFF);
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $cutoff) !== 1) {
            $cutoff = self::DEFAULT_CUTOFF;
        }

        return ['workingDays' => $workingDays, 'cutoff' => $cutoff];
    }
}
