<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class Status8TrackingMappingProvider
{
    private const CONFIG_KEY = 'LieferzeitenAdmin.config.status8CarrierMapping';
    private const DEFAULT_MAPPING_FILE = __DIR__ . '/../Resources/config/status8_tracking_mapping.php';

    public function __construct(private readonly SystemConfigService $config)
    {
    }

    public function isClosed(array $parcel, array $order = []): ?bool
    {
        $state = $this->normalizeState((string) ($parcel['trackingStatus'] ?? $parcel['san6Status'] ?? $parcel['status'] ?? $parcel['state'] ?? ''));
        if ($state === '') {
            return null;
        }

        $carrier = $this->resolveCarrier($parcel, $order);
        $mapping = $this->getResolvedMapping();

        if ($carrier !== '' && array_key_exists($state, $mapping['carriers'][$carrier] ?? [])) {
            return (bool) $mapping['carriers'][$carrier][$state];
        }

        if (array_key_exists($state, $mapping['global'])) {
            return (bool) $mapping['global'][$state];
        }

        return null;
    }

    /** @return array{version:int,global:array<string,bool>,carriers:array<string,array<string,bool>>} */
    public function getResolvedMapping(): array
    {
        $defaults = require self::DEFAULT_MAPPING_FILE;
        if (!is_array($defaults)) {
            return ['version' => 1, 'global' => [], 'carriers' => []];
        }

        $dbMapping = $this->readDbMapping();

        return [
            'version' => (int) ($dbMapping['version'] ?? $defaults['version'] ?? 1),
            'global' => $this->normalizeMap(array_merge($defaults['global'] ?? [], $dbMapping['global'] ?? [])),
            'carriers' => $this->mergeCarrierMap($defaults['carriers'] ?? [], $dbMapping['carriers'] ?? []),
        ];
    }

    /** @return array{version?:int,global?:array<string,mixed>,carriers?:array<string,array<string,mixed>>} */
    private function readDbMapping(): array
    {
        $raw = $this->config->get(self::CONFIG_KEY);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $map
     *  @return array<string,bool>
     */
    private function normalizeMap(array $map): array
    {
        $normalized = [];
        foreach ($map as $state => $value) {
            $key = $this->normalizeState((string) $state);
            if ($key === '') {
                continue;
            }

            $normalized[$key] = (bool) $value;
        }

        return $normalized;
    }

    /**
     * @param array<string,array<string,mixed>> $default
     * @param array<string,array<string,mixed>> $override
     *
     * @return array<string,array<string,bool>>
     */
    private function mergeCarrierMap(array $default, array $override): array
    {
        $result = [];
        $carrierNames = array_unique(array_merge(array_keys($default), array_keys($override)));

        foreach ($carrierNames as $carrier) {
            $carrierKey = $this->normalizeState((string) $carrier);
            if ($carrierKey === '') {
                continue;
            }

            $result[$carrierKey] = $this->normalizeMap(array_merge($default[$carrier] ?? [], $override[$carrier] ?? []));
        }

        return $result;
    }

    private function resolveCarrier(array $parcel, array $order): string
    {
        $carrier = (string) ($parcel['carrier']
            ?? $parcel['carrierCode']
            ?? $parcel['shippingCarrier']
            ?? $order['carrier']
            ?? $order['shipping']['carrier']
            ?? $order['shippingCarrier']
            ?? '');

        return $this->normalizeState($carrier);
    }

    private function normalizeState(string $state): string
    {
        $state = mb_strtolower(trim($state));

        return str_replace([' ', '-', '/'], '_', $state);
    }
}
