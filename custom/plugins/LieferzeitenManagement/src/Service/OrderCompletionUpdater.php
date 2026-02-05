<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service;

use LieferzeitenManagement\Core\Content\Package\LieferzeitenPackageDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class OrderCompletionUpdater
{
    private const ORDER_COMPLETED_STATUS = 'Bestellung abgeschlossen';

    /**
     * @param EntityRepository<LieferzeitenPackageDefinition> $packageRepository
     */
    public function __construct(private readonly EntityRepository $packageRepository)
    {
    }

    public function update(string $orderId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $packages = $this->packageRepository->search($criteria, $context);

        if ($packages->count() === 0) {
            return;
        }

        $allDelivered = true;

        foreach ($packages as $package) {
            if ($package->getDeliveredAt() === null) {
                $allDelivered = false;
                break;
            }
        }

        $payloads = [];

        if ($allDelivered) {
            foreach ($packages as $package) {
                if ($package->getPackageStatus() === self::ORDER_COMPLETED_STATUS) {
                    continue;
                }

                $payloads[] = [
                    'id' => $package->getId(),
                    'packageStatus' => self::ORDER_COMPLETED_STATUS,
                ];
            }
        } else {
            foreach ($packages as $package) {
                if ($package->getPackageStatus() !== self::ORDER_COMPLETED_STATUS) {
                    continue;
                }

                $payloads[] = [
                    'id' => $package->getId(),
                    'packageStatus' => null,
                ];
            }
        }

        if ($payloads !== []) {
            $this->packageRepository->upsert($payloads, $context);
        }
    }
}
