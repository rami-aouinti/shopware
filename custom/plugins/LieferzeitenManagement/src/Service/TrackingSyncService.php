<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service;

use LieferzeitenManagement\Core\Content\TrackingEvent\LieferzeitenTrackingEventDefinition;
use LieferzeitenManagement\Core\Content\TrackingNumber\LieferzeitenTrackingNumberDefinition;
use LieferzeitenManagement\Core\Notification\Event\LieferzeitenTrackingAvailableEvent;
use LieferzeitenManagement\Core\Notification\NotificationKey;
use LieferzeitenManagement\Service\Notification\NotificationEventDispatcher;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class TrackingSyncService
{
    /**
     * @param EntityRepository<LieferzeitenTrackingEventDefinition> $trackingEventRepository
     * @param EntityRepository<LieferzeitenTrackingNumberDefinition> $trackingNumberRepository
     */
    public function __construct(
        private readonly TrackingClient $trackingClient,
        private readonly EntityRepository $trackingEventRepository,
        private readonly EntityRepository $trackingNumberRepository,
        private readonly NotificationEventDispatcher $notificationEventDispatcher,
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

        $criteria = new Criteria([$trackingNumberId]);
        $criteria->addAssociation('package.order.orderCustomer');

        $trackingNumberEntity = $this->trackingNumberRepository->search($criteria, $context)->first();
        $order = $trackingNumberEntity?->getPackage()?->getOrder();
        $salesChannelId = $order?->getSalesChannelId();

        if (!$order || !$salesChannelId) {
            return;
        }

        $flowEvent = LieferzeitenTrackingAvailableEvent::createFromOrder($context, $order, $trackingNumber);
        $this->notificationEventDispatcher->dispatchIfEnabled(
            $flowEvent,
            $salesChannelId,
            NotificationKey::TRACKING_AVAILABLE,
            $context,
        );
    }
}
