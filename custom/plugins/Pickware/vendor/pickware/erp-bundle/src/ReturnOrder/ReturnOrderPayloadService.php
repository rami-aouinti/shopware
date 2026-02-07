<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemDefinition;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;

class ReturnOrderPayloadService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ProductNameFormatterService $productNameFormatterService,
    ) {}

    public function addDefaultValuesToLineItems(
        string $orderId,
        array &$lineItemPayloads,
        Context $context,
    ): void {
        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $orderId,
            $context,
            ['lineItems'],
        );

        // Copy over missing information from the order line items to the return order line items.
        foreach ($lineItemPayloads as &$lineItem) {
            $lineItem['orderLineItemId'] ??= null;

            /** @var OrderLineItemEntity $orderLineItem */
            $orderLineItem = $order
                ->getLineItems()
                ->filter(fn($orderLineItem) => $orderLineItem->getId() === $lineItem['orderLineItemId'])
                ->first();

            if ($orderLineItem !== null) {
                $lineItem = [
                    ...$this->createReturnOrderLineItemPayloadFromOrderLineItem($orderLineItem),
                    ...$lineItem,
                ];
            }
        }
        unset($lineItem);

        // Create the line item payload from the product as fallback when it does not reference an order line item.
        $productIds = array_column(array_values(array_filter(
            $lineItemPayloads,
            fn($lineItem) => (
                $lineItem['type'] === LineItem::PRODUCT_LINE_ITEM_TYPE
                && $lineItem['orderLineItemId'] === null
            ),
        )), 'productId');

        if (count($productIds) === 0) {
            $products = new ProductCollection();
            $productNames = [];
        } else {
            /** @var ProductCollection $products */
            $products = $this->entityManager->findBy(
                ProductDefinition::class,
                ['id' => $productIds],
                $context,
            );
            $productNames = $this->productNameFormatterService->getFormattedProductNames(
                $productIds,
                templateOptions: [],
                context: $context,
            );
        }

        foreach ($lineItemPayloads as &$lineItem) {
            if (isset($lineItem['productId']) && $lineItem['orderLineItemId'] === null) {
                $product = $products->get($lineItem['productId']);
                if ($product) {
                    $lineItem = [
                        'name' => $productNames[$product->getId()],
                        'productId' => $product->getId(),
                        'productNumber' => $product->getProductNumber(),
                        ...$lineItem,
                    ];
                }
            }
            $lineItem['reason'] ??= ReturnOrderLineItemDefinition::REASON_UNKNOWN;
            $lineItem['price'] ??= ReturnOrderPriceCalculationService::createEmptyCalculatedPrice();
            $lineItem['priceDefinition'] ??= (new QuantityPriceDefinition(
                price: 0,
                taxRules: new TaxRuleCollection(),
                quantity: $lineItem['quantity'],
            ))->jsonSerialize();
            if ($lineItem['priceDefinition'] instanceof Struct) {
                $lineItem['priceDefinition'] = $lineItem['priceDefinition']->jsonSerialize();
            }
            $lineItem['priceDefinition']['quantity'] = $lineItem['quantity'];
        }
        unset($lineItem);
    }

    private function createReturnOrderLineItemPayloadFromOrderLineItem(
        OrderLineItemEntity $orderLineItem,
    ): array {
        return [
            'type' => $orderLineItem->getType(),
            'name' => $orderLineItem->getLabel(),
            'productId' => $orderLineItem->getProductId(),
            'productNumber' => $orderLineItem->getPayload()['productNumber'] ?? null,
            'price' => $orderLineItem->getPrice(),
            // The price definition is nullable and may thus be missing. In such cases we reconstruct a quantity price
            // definition with the information still contained in the price and try to match the original price
            // definition as closely as possible.
            'priceDefinition' => $orderLineItem->getPriceDefinition()?->jsonSerialize() ?? new QuantityPriceDefinition(
                price: $orderLineItem->getPrice()->getUnitPrice(),
                taxRules: $orderLineItem->getPrice()->getTaxRules(),
                quantity: $orderLineItem->getQuantity(),
            ),
        ];
    }
}
