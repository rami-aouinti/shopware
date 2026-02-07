<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Cache;

use Pickware\PickwareErpStarter\Stock\ProductAvailableStockUpdatedEvent;
use Pickware\PickwareErpStarter\Stock\StockUpdatedForStockMovementsEvent;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

class CacheInvalidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        public readonly CacheInvalidationService $cacheInvalidationService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ProductAvailableStockUpdatedEvent::class => [
                'onProductAvailableStockUpdated',
                PHP_INT_MIN,
            ],

            StockUpdatedForStockMovementsEvent::class => [
                'onStockUpdatedForStockMovements',
                10000, // Set a high priority to execute the invalidation of the cache before any other updates are written
            ],

            KernelEvents::TERMINATE => [
                'clearDeferredCache',
            ],
            ConsoleEvents::TERMINATE => [
                'clearDeferredCache',
            ],
            WorkerMessageHandledEvent::class => [
                'clearDeferredCache',
            ],
        ];
    }

    public function onProductAvailableStockUpdated(ProductAvailableStockUpdatedEvent $event): void
    {
        $this->cacheInvalidationService->invalidateProductCache($event->getProductIds());
    }

    public function onStockUpdatedForStockMovements(StockUpdatedForStockMovementsEvent $event): void
    {
        $productIds = array_values(array_map(
            fn(array $stockMovement) => $stockMovement['productId'],
            $event->getStockMovements(),
        ));
        $this->cacheInvalidationService->invalidateProductStreams($productIds);
        $this->cacheInvalidationService->invalidateProductListingRoute($productIds);
    }

    public function clearDeferredCache(TerminateEvent|ConsoleTerminateEvent|WorkerMessageHandledEvent $event): void
    {
        $this->cacheInvalidationService->invalidateCacheDeferred();
    }
}
