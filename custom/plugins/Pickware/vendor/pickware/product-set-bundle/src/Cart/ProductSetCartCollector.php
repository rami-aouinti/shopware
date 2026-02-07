<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Cart;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\ProductSetBundle\FeatureFlag\ProductSetFeatureFlag;
use Pickware\ProductSetBundle\Model\ProductSetDefinition;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductSetCartCollector implements CartDataCollectorInterface, CartProcessorInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityCacheKeyGenerator $generator,
        private readonly QuantityPriceCalculator $calculator,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    public function validateAvailableStock(Cart $cart): void
    {
        if (!$this->featureFlagService->isActive(ProductSetFeatureFlag::NAME)) {
            return;
        }

        // Only product order line items keep the product id as `referenceId`. Even though it should not happen in
        // production, the `referenceId` is not validated anywhere. To make sure we can `hex2bin` it for sql, we apply a
        // Uuid filter on it in addition to make extra sure to never fail a cart collection (e.g. an order
        // recalculation).
        $productLineItemReferenceIds = $cart->getLineItems()
            ->filter(fn(LineItem $orderLineItem) => $orderLineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE
                && $orderLineItem->getReferencedId() !== null
                && Uuid::isValid($orderLineItem->getReferencedId()))
            ->getReferenceIds();

        if (count($productLineItemReferenceIds) === 0) {
            return;
        }

        // Note: Use LEFT JOIN to fetch sub product availability data when a sub product is in the cart by itself (not
        // part of a set product).
        $orderLineItemWithSubProductsAvailability = $this->connection->fetchAllAssociative(
            'SELECT

                LOWER(HEX(orderLineItemProduct.`id`)) as orderLineItemProductId,
                IFNULL(LOWER(HEX(subProduct.`id`)), LOWER(HEX(orderLineItemProduct.`id`))) as productId,
                IFNULL(subProduct.`available_stock`, orderLineItemProduct.`available_stock`) as availableStock,
                IFNULL(subProduct.`is_closeout`, orderLineItemProduct.`is_closeout`) as isCloseout,

                # The following properties are NULL for non-product-set order line items
                productSetConfiguration.`quantity` as subProductQuantity,
                LOWER(HEX(productSet.`product_id`)) as productSetProductId

            FROM `product` orderLineItemProduct

            LEFT JOIN `pickware_product_set_product_set` productSet
            ON productSet.`product_id` = orderLineItemProduct.`id`

            LEFT JOIN `pickware_product_set_product_set_configuration` productSetConfiguration
            ON productSetConfiguration.`product_set_id` = productSet.`id`

            LEFT JOIN `product` subProduct
            ON subProduct.`id` = productSetConfiguration.`product_id`

            WHERE orderLineItemProduct.`id` IN (:productIds)
            ORDER BY orderLineItemProduct.`id`',
            ['productIds' => array_map('hex2bin', $productLineItemReferenceIds)],
            ['productIds' => ArrayParameterType::STRING],
        );

        if (count(array_filter(array_column($orderLineItemWithSubProductsAvailability, 'productSetProductId'), fn($value) => $value !== null)) === 0) {
            // If no product set is in the cart, we can early return because the availability is already checked by
            // shopware's validation.
            return;
        }

        $aggregatedAvailabilityByProductId = [];
        foreach ($orderLineItemWithSubProductsAvailability as $orderLineItemAvailability) {
            // When a line item is added to the cart via the CartService (which is the way the storefronts adds line items to the cart)
            // the collection of line items is indexed by the referenceId of the line item (respectively the productId). When
            // a line item is added via the add() method of the collection, the line item is indexed by its own id. If it is
            // indexed by the referenceId, we ignore it.
            if (!array_key_exists($orderLineItemAvailability['orderLineItemProductId'], $cart->getLineItems()->getElements())) {
                continue;
            }

            $productId = $orderLineItemAvailability['productId'];
            if (!array_key_exists($productId, $aggregatedAvailabilityByProductId)) {
                $aggregatedAvailabilityByProductId[$productId] = [
                    'quantity' => 0,
                    'availableStock' => (int) $orderLineItemAvailability['availableStock'],
                    'isCloseout' => (bool) $orderLineItemAvailability['isCloseout'],
                    'productSetProductId' => $orderLineItemAvailability['productSetProductId'],
                    'productSetConfigurationQuantity' => $orderLineItemAvailability['subProductQuantity'],
                ];
            }

            $orderLineItemQuantity = $cart->getLineItems()->getElements()[$orderLineItemAvailability['orderLineItemProductId']]->getQuantity();

            $isProductSetLineItem = $orderLineItemAvailability['productSetProductId'] !== null;
            if ($isProductSetLineItem) {
                // If the sub product was added separately (own separate line item), it was initialized in
                // $aggregatedAvailabilityByProductId without product set values. We need to set them now (and
                // overwrite any existing values).
                $aggregatedAvailabilityByProductId[$productId]['productSetProductId'] = $orderLineItemAvailability['productSetProductId'];
                $aggregatedAvailabilityByProductId[$productId]['productSetConfigurationQuantity'] = $orderLineItemAvailability['subProductQuantity'];
            }

            // Sub products of set products are multiplied by their sub product quantity whereas all other order line
            // items (including sub product that are not part of a product set) are added with their regular order line
            // item quantity.
            $orderLineItemQuantityFactor = $isProductSetLineItem ? $orderLineItemAvailability['subProductQuantity'] : 1;
            $aggregatedAvailabilityByProductId[$productId]['quantity'] += $orderLineItemQuantityFactor * $orderLineItemQuantity;
        }

        foreach ($aggregatedAvailabilityByProductId as $lineItem) {
            if (
                $lineItem['isCloseout']
                && isset($lineItem['productSetProductId'])
                && ($lineItem['availableStock'] < $lineItem['quantity'])
            ) {
                $productSetOrderLineItem = $cart->getLineItems()->get($lineItem['productSetProductId']);
                $quantityOfSubProductInProductSet = $lineItem['productSetConfigurationQuantity'] * $productSetOrderLineItem->getQuantity();
                $availableStockForOrder = max((int) (($quantityOfSubProductInProductSet - ($lineItem['quantity'] - $lineItem['availableStock'])) / $lineItem['productSetConfigurationQuantity']), 0);

                // When the available stock is less than the quantity of the product ordered, we need to adjust the
                // quantity of the product set order line item. If the available stock is 0 or less, we remove the
                // product set order line item from the cart.
                if ($availableStockForOrder > 0) {
                    $cart->getLineItems()->get($productSetOrderLineItem->getId())?->setQuantity($availableStockForOrder);
                } else {
                    $cart->getLineItems()->remove($productSetOrderLineItem->getId());
                }

                $cart->addErrors(new ProductSetNotAvailableError(
                    $productSetOrderLineItem->getId(),
                    $productSetOrderLineItem->getLabel(),
                    $availableStockForOrder,
                ));
            }
        }
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $this->validateAvailableStock($original);
    }

    // This process handles the price calculation of product sets like Shopware does with products (which is our intended behavior).
    // See: https://github.com/shopware/shopware/blob/90e292c5b08ebe0b998996ae671946cb4553255c/src/Core/Content/Product/Cart/ProductCartProcessor.php#L115
    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $hash = $this->generator->getSalesChannelContextHash($context);

        $items = $original->getLineItems()->filterType(ProductSetDefinition::LINE_ITEM_TYPE);

        if (count($items) === 0) {
            return;
        }

        foreach ($items as $item) {
            $definition = $item->getPriceDefinition();

            if (!$definition instanceof QuantityPriceDefinition) {
                throw CartException::missingLineItemPrice($item->getId());
            }
            $definition->setQuantity($item->getQuantity());

            $item->setPrice($this->calculator->calculate($definition, $context));
            $item->setDataContextHash($hash);

            $toCalculate->add($item);
        }
    }
}
