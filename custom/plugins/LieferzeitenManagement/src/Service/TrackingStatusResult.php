<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service;

final class TrackingStatusResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $deliveredAt
    ) {
    }
}
