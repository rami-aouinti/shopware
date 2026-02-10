<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Tracking;

interface TrackingClientInterface
{
    public function supportsCarrier(string $carrier): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchHistory(string $trackingNumber): array;
}
