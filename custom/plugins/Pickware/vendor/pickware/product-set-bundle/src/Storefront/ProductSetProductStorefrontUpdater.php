<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Storefront;

use Shopware\Core\Content\Product\Events\ProductGatewayCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPageCriteriaEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSetProductStorefrontUpdater implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel.' . ProductEvents::PRODUCT_LOADED_EVENT => [
                'onSalesChannelProductLoaded',
                PHP_INT_MAX, // Ensure that this subscriber is executed after the original subscriber
            ],
            ProductGatewayCriteriaEvent::class => 'addProductSetExtensionToCriteria',
            ProductPageCriteriaEvent::class => 'addProductSetExtensionToCriteria',
            ProductSearchCriteriaEvent::class => 'addProductSetExtensionToCriteria',
        ];
    }

    public function addProductSetExtensionToCriteria(
        ProductGatewayCriteriaEvent|ProductPageCriteriaEvent|ProductSearchCriteriaEvent $event,
    ): void {
        $criteria = $event->getCriteria();
        $criteria->addAssociation('pickwareProductSetProductSet');
    }

    public function onSalesChannelProductLoaded(SalesChannelEntityLoadedEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        /**
         * @var SalesChannelProductEntity[] $productSetProducts
         */
        $productSetProducts = [];
        foreach ($event->getEntities() as $product) {
            if (!$product instanceof SalesChannelProductEntity || !array_key_exists('pickwareProductSetProductSet', $product->getExtensions())) {
                continue;
            }

            $productSetProducts[] = $product;
        }

        $this->updateProductSetAvailabilityForStorefront($productSetProducts);
    }

    /**
     * @param SalesChannelProductEntity[] $productSetProducts
     */
    private function updateProductSetAvailabilityForStorefront(array $productSetProducts): void
    {
        if (count($productSetProducts) === 0) {
            return;
        }

        // The product set `closeout` is set by the user, not by our subscribers.
        // If the product set is available (e.g. `closeout` is false), but the assigned products are not available, that
        // product set would be displayed available in the storefront and would cause an error if the customer adds it to
        // the cart.
        // To prevent this, we need to explicitly set the product sets `closeout` to true and `calculatedMaxPurchase` to 0 if the
        // product set should not be available based on its assigned products.
        foreach ($productSetProducts as $productSetProduct) {
            if ($productSetProduct->getAvailable()) {
                continue;
            }

            $productSetProduct->setIsCloseout(true);
            $productSetProduct->setCalculatedMaxPurchase(0);
        }

        // As of this commit (https://github.com/shopware/shopware/commit/66ef6e49feef631f1786b3b8735b83f1cf4b321a#diff-7de199cab74be0dcc27eab6f5f57dfa1b44045c24ce6c07c845aa98563ca0a14)
        // shopware uses stock and available stock properties as mirrors of each other. The _stock_ is now used in the
        // Storefront and not the available stock.
        // The product set bundle manipulates the available stock _and_ the stock property only recently. To be
        // backwards compatible and without creating a migration we can just overwrite the stock property with the
        // available stock here. This section can be removed in next major release product-set-3.0.0.
        foreach ($productSetProducts as $productSetProduct) {
            $productSetProduct->setStock($productSetProduct->getAvailableStock());
        }
    }
}
