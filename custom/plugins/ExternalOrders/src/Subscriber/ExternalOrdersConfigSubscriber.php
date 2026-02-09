<?php declare(strict_types=1);

namespace ExternalOrders\Subscriber;

use ExternalOrders\Service\ExternalOrderSyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExternalOrdersConfigSubscriber implements EventSubscriberInterface
{
    private const WATCHED_KEYS = [
        'ExternalOrders.config.externalOrdersApiUrlB2b',
        'ExternalOrders.config.externalOrdersApiTokenB2b',
        'ExternalOrders.config.externalOrdersApiUrlEbayDe',
        'ExternalOrders.config.externalOrdersApiTokenEbayDe',
        'ExternalOrders.config.externalOrdersApiUrlKaufland',
        'ExternalOrders.config.externalOrdersApiTokenKaufland',
        'ExternalOrders.config.externalOrdersApiUrlEbayAt',
        'ExternalOrders.config.externalOrdersApiTokenEbayAt',
        'ExternalOrders.config.externalOrdersApiUrlZonami',
        'ExternalOrders.config.externalOrdersApiTokenZonami',
        'ExternalOrders.config.externalOrdersApiUrlPeg',
        'ExternalOrders.config.externalOrdersApiTokenPeg',
        'ExternalOrders.config.externalOrdersApiUrlBezb',
        'ExternalOrders.config.externalOrdersApiTokenBezb',
    ];

    public function __construct(private readonly ExternalOrderSyncService $syncService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigChangedEvent::class => 'onConfigChanged',
        ];
    }

    public function onConfigChanged(SystemConfigChangedEvent $event): void
    {
        if (!in_array($event->getKey(), self::WATCHED_KEYS, true)) {
            return;
        }

        $this->syncService->syncNewOrders(Context::createDefaultContext());
    }
}
