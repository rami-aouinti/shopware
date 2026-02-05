<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service;

use LieferzeitenManagement\Core\Content\Package\LieferzeitenPackageDefinition;
use LieferzeitenManagement\Core\Content\TrackingNumber\LieferzeitenTrackingNumberDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

class San6SyncService
{
    /**
     * @param EntityRepository<LieferzeitenPackageDefinition> $packageRepository
     * @param EntityRepository<LieferzeitenTrackingNumberDefinition> $trackingNumberRepository
     */
    public function __construct(
        private readonly San6Client $san6Client,
        private readonly EntityRepository $packageRepository,
        private readonly EntityRepository $trackingNumberRepository
    ) {
    }

    public function sync(Context $context): void
    {
        $packages = $this->san6Client->fetchPackages();

        if ($packages === []) {
            return;
        }

        $packagePayloads = [];
        $trackingPayloads = [];

        foreach ($packages as $package) {
            if (!isset($package['orderId'])) {
                continue;
            }

            $packageId = $package['id'] ?? Uuid::randomHex();

            $packagePayloads[] = [
                'id' => $packageId,
                'orderId' => $package['orderId'],
                'san6PackageNumber' => $package['san6PackageNumber'] ?? null,
                'packageStatus' => $package['packageStatus'] ?? null,
                'shippedAt' => $package['shippedAt'] ?? null,
                'deliveredAt' => $package['deliveredAt'] ?? null,
                'trackingNumber' => $package['trackingNumber'] ?? null,
                'trackingProvider' => $package['trackingProvider'] ?? null,
                'trackingStatus' => $package['trackingStatus'] ?? null,
            ];

            if (!empty($package['trackingNumber'])) {
                $trackingPayloads[] = [
                    'id' => Uuid::randomHex(),
                    'packageId' => $packageId,
                    'trackingNumber' => $package['trackingNumber'],
                    'trackingProvider' => $package['trackingProvider'] ?? null,
                    'isActive' => true,
                ];
            }
        }

        if ($packagePayloads !== []) {
            $this->packageRepository->upsert($packagePayloads, $context);
        }

        if ($trackingPayloads !== []) {
            $this->trackingNumberRepository->upsert($trackingPayloads, $context);
        }
    }
}
