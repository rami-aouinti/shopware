<?php declare(strict_types=1);

namespace LieferzeitenManagement\Service\Notification;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEventAware;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class NotificationEventDispatcher
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NotificationSettingsResolver $settingsResolver,
    ) {
    }

    public function dispatchIfEnabled(FlowEventAware $event, string $salesChannelId, string $notificationKey, Context $context): void
    {
        if (!$this->settingsResolver->isEnabled($salesChannelId, $notificationKey, $context)) {
            return;
        }

        $this->eventDispatcher->dispatch($event, $event->getName());
    }
}
