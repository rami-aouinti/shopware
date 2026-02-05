<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service;

use LieferzeitenManagement\Core\Content\TrackingEvent\LieferzeitenTrackingEventDefinition;
use LieferzeitenManagement\Core\Content\TrackingNumber\LieferzeitenTrackingNumberDefinition;
use LieferzeitenManagement\Core\Content\Package\LieferzeitenPackageDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class TrackingSyncService
{
    /**
     * @param EntityRepository<LieferzeitenTrackingEventDefinition> $trackingEventRepository
     * @param EntityRepository<LieferzeitenTrackingNumberDefinition> $trackingNumberRepository
     * @param EntityRepository<LieferzeitenPackageDefinition> $packageRepository
     */
    public function __construct(
        private readonly TrackingClient $trackingClient,
        private readonly EntityRepository $trackingEventRepository,
        private readonly EntityRepository $trackingNumberRepository,
        private readonly EntityRepository $packageRepository,
        private readonly TrackingStatusInterpreter $trackingStatusInterpreter,
        private readonly OrderCompletionUpdater $orderCompletionUpdater
    ) {
    }

    public function syncTrackingNumber(string $trackingNumberId, string $trackingNumber, Context $context): void
    {
        $events = $this->trackingClient->fetchTrackingEvents($trackingNumber);

        if ($events !== []) {
            $payloads = [];

            foreach ($events as $event) {
                $payloads[] = [
                    'id' => $event['id'] ?? Uuid::randomHex(),
                    'trackingNumberId' => $trackingNumberId,
                    'status' => $event['status'] ?? null,
                    'description' => $event['description'] ?? null,
                    'occurredAt' => $event['occurredAt'] ?? null,
                    'payload' => $event,
                ];
            }

            $this->trackingEventRepository->upsert($payloads, $context);
        }

        $criteria = new Criteria([$trackingNumberId]);
        $criteria->addAssociation('package');

        $trackingNumberEntity = $this->trackingNumberRepository->search($criteria, $context)->first();
        $package = $trackingNumberEntity?->getPackage();

        if (!$package || !$package->getId()) {
            return;
        }

        $fallbackDeliveredAt = $package->getDeliveredAt()?->format(DATE_ATOM);
        $result = $this->trackingStatusInterpreter->interpret(
            $events,
            $package->getTrackingStatus(),
            $fallbackDeliveredAt
        );

        $this->packageRepository->upsert([
            [
                'id' => $package->getId(),
                'trackingStatus' => $result->status,
                'deliveredAt' => $result->deliveredAt,
            ],
        ], $context);

        if ($package->getOrderId()) {
            $this->orderCompletionUpdater->update($package->getOrderId(), $context);
        }
    }
}
