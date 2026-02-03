<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\ParcelHydration\Processor;

use Pickware\ShippingBundle\Notifications\NotificationService;
use Pickware\ShippingBundle\Parcel\ParcelItem;
use Pickware\ShippingBundle\ParcelHydration\OrderLineItemParcelMapping;
use Pickware\ShippingBundle\ParcelHydration\ParcelHydrationNotification;
use Pickware\ShippingBundle\ParcelHydration\ParcelItemHydrator;
use Pickware\UnitsOfMeasurement\Dimensions\BoxDimensions;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Length;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Weight;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;

/**
 * This processor creates a `ParcelItem` for each order line item that represents a product
 * or a product set. Mapping is necessary so that these items can be further processed
 * (e.g. distributing prices, customs info, etc.).
 */
class CreateParcelItemsProcessor implements ParcelItemsProcessor
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * @param OrderLineItemParcelMapping[] $items
     * @return OrderLineItemParcelMapping[]
     */
    public function process(array $items, ProcessorContext $processorContext): array
    {
        foreach ($items as $item) {
            $orderLineItem = $item->getOrderLineItem();

            $orderLineItemType = $orderLineItem->getType();
            if ($orderLineItemType !== LineItem::PRODUCT_LINE_ITEM_TYPE && $orderLineItemType !== ParcelItemHydrator::PRODUCT_SET_TYPE) {
                continue;
            }

            $parcelItem = new ParcelItem($orderLineItem->getQuantity());
            $item->setParcelItem($parcelItem);
            $product = $orderLineItem->getProduct();

            if (!$product) {
                $parcelItem->setName($orderLineItem->getLabel());
                $parcelItem->setCustomsDescription($orderLineItem->getLabel());
                $this->notificationService->emit(ParcelHydrationNotification::productWasDeleted(
                    $processorContext->getOrderNumber(),
                    $orderLineItem->getLabel(),
                ));

                continue;
            }

            $productName = $product->getName() ?: $product->getTranslation('name');
            $parcelItem->setName($productName);
            $parcelItem->setUnitWeight($product->getWeight() !== null ? new Weight($product->getWeight(), 'kg') : null);
            $parcelItem->setProductNumber($product->getProductNumber());

            if (
                ($product->getWidth() ?? 0.0) > 0.0
                && ($product->getHeight() ?? 0.0) > 0.0
                && ($product->getLength() ?? 0.0) > 0.0
            ) {
                $parcelItem->setUnitDimensions(new BoxDimensions(
                    new Length($product->getWidth(), 'mm'),
                    new Length($product->getHeight(), 'mm'),
                    new Length($product->getLength(), 'mm'),
                ));
            }
        }

        return $items;
    }
}
