<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service;

use LieferzeitenManagement\Core\Content\Package\LieferzeitenPackageDefinition;
use LieferzeitenManagement\Core\Content\TrackingNumber\LieferzeitenTrackingNumberDefinition;
use LieferzeitenManagement\Service\Deadline\DeadlineResolver;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class San6SyncService
{
    /**
     * @param EntityRepository<LieferzeitenPackageDefinition> $packageRepository
     * @param EntityRepository<LieferzeitenTrackingNumberDefinition> $trackingNumberRepository
     * @param EntityRepository<OrderDefinition> $orderRepository
     */
    public function __construct(
        private readonly San6Client $san6Client,
        private readonly EntityRepository $packageRepository,
        private readonly EntityRepository $trackingNumberRepository,
        private readonly EntityRepository $orderRepository,
        private readonly DeadlineResolver $deadlineResolver
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

            if ($this->isTestOrder($package['orderId'], $context)) {
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
                ...$this->deadlineResolver->resolveForOrder($package['orderId'], $context),
            ];

            if (!empty($package['trackingNumber'])) {
                $this->deactivateExistingTrackingNumbers($packageId, $context);

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

    private function isTestOrder(string $orderId, Context $context): bool
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('orderCustomer');

        $order = $this->orderRepository->search($criteria, $context)->first();

        if (!$order) {
            return false;
        }

        $orderNumber = strtoupper((string) $order->getOrderNumber());
        $customerEmail = strtolower((string) $order->getOrderCustomer()?->getEmail());

        return str_contains($orderNumber, 'TEST') || str_contains($customerEmail, 'test');
    }

    private function deactivateExistingTrackingNumbers(string $packageId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('packageId', $packageId));
        $criteria->addFilter(new EqualsFilter('isActive', true));

        $existing = $this->trackingNumberRepository->search($criteria, $context);

        if ($existing->count() === 0) {
            return;
        }

        $payload = [];
        foreach ($existing as $trackingNumber) {
            $payload[] = [
                'id' => $trackingNumber->getId(),
                'isActive' => false,
            ];
        }

        $this->trackingNumberRepository->upsert($payload, $context);
    }
}
