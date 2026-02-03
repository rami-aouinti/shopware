<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt;

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptCollection;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptEntity;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemEntity;
use Pickware\PickwareErpStarter\PriceCalculation\CartPriceCalculator;
use Pickware\PickwareErpStarter\PriceCalculation\PriceCalculationContext;
use Pickware\PickwareErpStarter\PriceCalculation\PriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Framework\Context;

class GoodsReceiptPriceCalculationService
{
    private EntityManager $entityManager;
    private PriceCalculator $priceCalculator;
    private CartPriceCalculator $cartPriceCalculator;

    public function __construct(
        EntityManager $entityManager,
        PriceCalculator $priceCalculator,
        CartPriceCalculator $cartPriceCalculator,
    ) {
        $this->entityManager = $entityManager;
        $this->priceCalculator = $priceCalculator;
        $this->cartPriceCalculator = $cartPriceCalculator;
    }

    public function recalculateGoodsReceipts(array $goodsReceiptIds, Context $context): void
    {
        /** @var GoodsReceiptCollection $goodsReceipts */
        $goodsReceipts = $this->entityManager->findBy(
            GoodsReceiptDefinition::class,
            ['id' => $goodsReceiptIds],
            $context,
            ['lineItems'],
        );
        if (count($goodsReceiptIds) > $goodsReceipts->count()) {
            throw new InvalidArgumentException(
                sprintf(
                    'Goods receipts with the IDs "%s" were not found',
                    implode(', ', array_diff($goodsReceiptIds, $goodsReceipts->getIds())),
                ),
            );
        }

        $errors = array_values($goodsReceipts->fmap(function(GoodsReceiptEntity $goodsReceipt) {
            // If the rounding configuration is missing _no_ price can be recalculated (goods receipt price or line
            // item prices).
            if (!$goodsReceipt->getItemRounding() || !$goodsReceipt->getTotalRounding()) {
                return GoodsReceiptError::roundingConfigurationMissing(goodsReceiptId: $goodsReceipt->getId());
            }

            return null;
        }));

        if (count($errors) !== 0) {
            throw new GoodsReceiptException(new JsonApiErrors($errors));
        }

        $updatePayloads = [];
        /** @var GoodsReceiptEntity $goodsReceipt */
        foreach ($goodsReceipts as $goodsReceipt) {
            $priceCalculationContext = new PriceCalculationContext(
                taxStatus: CartPrice::TAX_STATE_NET, // Currently we only support net orders
                itemRounding: $goodsReceipt->getItemRounding(),
                totalRounding: $goodsReceipt->getTotalRounding(),
            );

            $goodsReceiptLineItemUpdatePayloads = [];
            $lineItemPrices = new PriceCollection();
            /** @var GoodsReceiptLineItemEntity $goodsReceiptLineItem */
            foreach ($goodsReceipt->getLineItems() as $goodsReceiptLineItem) {
                // Recalculate each goods receipt line item price if the line item has a price definition
                $priceDefinition = $goodsReceiptLineItem->getPriceDefinition();
                if (!$priceDefinition) {
                    continue;
                }
                // The PriceCalculator only supports QuantityPriceDefinition (as for now)
                if ($priceDefinition->getType() !== QuantityPriceDefinition::TYPE) {
                    $lineItemPrices->add($goodsReceiptLineItem->getPrice());

                    continue;
                }
                // For goods receipts we explicitly save a quantity. Therefore, we need to update the quantity of the
                // price definition.
                $priceDefinition->setQuantity($goodsReceiptLineItem->getQuantity());
                $newLineItemPrice = $this->priceCalculator->calculateQuantityPrice(
                    $priceDefinition,
                    $priceCalculationContext,
                );
                $goodsReceiptLineItemUpdatePayloads[] = [
                    'id' => $goodsReceiptLineItem->getId(),
                    'price' => $newLineItemPrice,
                    'priceDefinition' => $priceDefinition,
                ];
                $lineItemPrices->add($newLineItemPrice);
            }

            // Only calculate the goods receipt (total) price if there are line item prices
            $cartPrice = null;
            if (count($lineItemPrices) > 0) {
                $cartPrice = $this->cartPriceCalculator->calculateCartPrice($lineItemPrices, $priceCalculationContext);
            }

            $updatePayloads[] = [
                'id' => $goodsReceipt->getId(),
                'price' => $cartPrice,
                'lineItems' => $goodsReceiptLineItemUpdatePayloads,
            ];
        }

        $this->entityManager->update(GoodsReceiptDefinition::class, $updatePayloads, $context);
    }
}
