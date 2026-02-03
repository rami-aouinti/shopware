<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater as ShopwareProductStreamUpdater;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductStreamUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly ShopwareProductStreamUpdater $productStreamUpdater,
        // The parameter was only introduced with SW 6.6.10, in any previous Versions it will be null
        #[Autowire(param: 'shopware.product_stream.indexing')]
        private readonly ?bool $indexProductStreams = null,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ProductAvailableStockUpdatedEvent::class => 'productAvailableStockUpdated',
        ];
    }

    public function productAvailableStockUpdated(ProductAvailableStockUpdatedEvent $event): void
    {
        // Product streams can use the stock as a filter. Because of this we need to update the product stream
        // mappings via the productStreamUpdater to make sure dynamic product groups are updated.
        // For further reference see https://github.com/pickware/shopware-plugins/issues/3232
        if ($this->indexProductStreams !== false) {
            $this->productStreamUpdater->updateProducts($event->getProductIds(), $event->getContext());
        }
    }
}
