<?php declare(strict_types=1);

namespace LieferzeitenManagement\Subscriber;

use LieferzeitenManagement\Core\Content\DateHistory\LieferzeitenDateHistoryDefinition;
use LieferzeitenManagement\Core\Notification\Event\LieferzeitenDeliveryDateChangedEvent;
use LieferzeitenManagement\Core\Notification\NotificationKey;
use LieferzeitenManagement\Service\Notification\NotificationEventDispatcher;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LieferzeitenDateHistoryWrittenSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<LieferzeitenDateHistoryDefinition> $dateHistoryRepository
     */
    public function __construct(
        private readonly EntityRepository $dateHistoryRepository,
        private readonly NotificationEventDispatcher $notificationEventDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LieferzeitenDateHistoryDefinition::ENTITY_NAME . '.written' => 'onDateHistoryWritten',
        ];
    }

    public function onDateHistoryWritten(EntityWrittenEvent $event): void
    {
        $ids = $event->getIds();

        if ($ids === []) {
            return;
        }

        $criteria = new Criteria($ids);
        $criteria->addAssociation('orderPosition.order.orderCustomer');
        $criteria->addAssociation('package.order.orderCustomer');

        $histories = $this->dateHistoryRepository->search($criteria, $event->getContext());

        foreach ($histories as $history) {
            $type = $history->getType();

            if (!in_array($type, ['supplier_delivery', 'new_delivery'], true)) {
                continue;
            }

            $order = $history->getOrderPosition()?->getOrder() ?? $history->getPackage()?->getOrder();
            $salesChannelId = $order?->getSalesChannelId();

            if (!$order || !$salesChannelId) {
                continue;
            }

            $rangeStart = $history->getRangeStart()?->format('Y-m-d');
            $rangeEnd = $history->getRangeEnd()?->format('Y-m-d');

            $flowEvent = LieferzeitenDeliveryDateChangedEvent::createFromOrder(
                $event->getContext(),
                $order,
                $type,
                $rangeStart,
                $rangeEnd,
            );

            $this->notificationEventDispatcher->dispatchIfEnabled(
                $flowEvent,
                $salesChannelId,
                NotificationKey::DELIVERY_DATE_CHANGED,
                $event->getContext(),
            );
        }
    }
}
