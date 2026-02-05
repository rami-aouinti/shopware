<?php declare(strict_types=1);

namespace LieferzeitenManagement\Subscriber;

use LieferzeitenManagement\Core\Notification\Event\LieferzeitenOrderCreatedEvent;
use LieferzeitenManagement\Core\Notification\NotificationKey;
use LieferzeitenManagement\Service\Notification\NotificationEventDispatcher;
use Shopware\Core\Checkout\Order\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LieferzeitenOrderPlacedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationEventDispatcher $notificationEventDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $order = $event->getOrder();
        $salesChannelId = $order->getSalesChannelId();

        if (!$salesChannelId) {
            return;
        }

        $flowEvent = LieferzeitenOrderCreatedEvent::createFromOrder($event->getContext(), $order);
        $this->notificationEventDispatcher->dispatchIfEnabled(
            $flowEvent,
            $salesChannelId,
            NotificationKey::ORDER_CREATED,
            $event->getContext(),
        );
    }
}
