<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service;

use LieferzeitenManagement\Core\Content\TrackingEvent\LieferzeitenTrackingEventDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

class TrackingSyncService
{
    /**
     * @param EntityRepository<LieferzeitenTrackingEventDefinition> $trackingEventRepository
     */
    public function __construct(
        private readonly TrackingClient $trackingClient,
        private readonly EntityRepository $trackingEventRepository
    ) {
    }

    public function syncTrackingNumber(string $trackingNumberId, string $trackingNumber, Context $context): void
    {
        $events = $this->trackingClient->fetchTrackingEvents($trackingNumber);

        if ($events === []) {
            return;
        }

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
}
