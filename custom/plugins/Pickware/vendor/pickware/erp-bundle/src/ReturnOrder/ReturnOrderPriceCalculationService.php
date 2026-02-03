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
use Pickware\PickwareErpStarter\PriceCalculation\CartPriceCalculator;
use Pickware\PickwareErpStarter\PriceCalculation\PriceCalculationContext;
use Pickware\PickwareErpStarter\PriceCalculation\PriceCalculator;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Context;

class ReturnOrderPriceCalculationService
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

    public function recalculateReturnOrders(array $returnOrderIds, Context $context): void
    {
        /** @var ReturnOrderCollection $returnOrders */
        $returnOrders = $this->entityManager->findBy(
            ReturnOrderDefinition::class,
            ['id' => $returnOrderIds],
            $context,
            [
                'lineItems.orderLineItem',
                'refund',
                'order',
            ],
        );
        if (count($returnOrderIds) > $returnOrders->count()) {
            ReturnOrderException::returnOrderNotFound($returnOrderIds, $returnOrders->getKeys());
        }

        $updatePayloads = [];
        foreach ($returnOrders as $returnOrder) {
            $priceCalculationContext = new PriceCalculationContext(
                $returnOrder->getTaxStatus(),
                $returnOrder->getOrder()->getItemRounding(),
                $returnOrder->getOrder()->getTotalRounding(),
            );

            $returnOrderLineItemUpdatePayloads = [];
            $lineItemPrices = new PriceCollection();
            foreach ($returnOrder->getLineItems() as $returnOrderLineItem) {
                $newLineItemPrice = $returnOrderLineItem->getPrice();

                if ($returnOrderLineItem->getPriceDefinition()->getType() === QuantityPriceDefinition::TYPE) {
                    $newLineItemPrice = $this->priceCalculator->calculateQuantityPrice(
                        $returnOrderLineItem->getPriceDefinition(),
                        $priceCalculationContext,
                    );
                }
                if ($returnOrderLineItem->getPriceDefinition()->getType() === AbsolutePriceDefinition::TYPE) {
                    // AbsolutePriceDefinition cannot be recalculated "correctly", because we would need to build new
                    // tax rules for a recalculation.
                    // Since the return order line item is initially copied with the (non-recalculated) tax rules from
                    // the original order line item, we can use the tax rules from the original order line item again.
                    // This is not 100% correct. But a "correct" recalculation was never done and will not be done here.
                    // If the original order line item does not exist anymore, no tax rules will be used.
                    $newLineItemPrice = $this->priceCalculator->calculateAbsolutePrice(
                        $returnOrderLineItem->getPriceDefinition(),
                        $returnOrderLineItem->getQuantity(),
                        $returnOrderLineItem->getOrderLineItem() ? $returnOrderLineItem->getOrderLineItem()->getPrice()->getTaxRules() : new TaxRuleCollection(),
                        $priceCalculationContext,
                    );
                }
                // Non-QuantityPriceDefinition and Non-AbsolutePriceDefinition types are not recalculated and stay the
                // same. (i.e. PercentagePriceDefinition)

                $returnOrderLineItemUpdatePayloads[] = [
                    'id' => $returnOrderLineItem->getId(),
                    'price' => $newLineItemPrice,
                ];
                $lineItemPrices->add($newLineItemPrice);
            }

            $cartPrice = $this->cartPriceCalculator->calculateCartPrice(
                $lineItemPrices,
                $priceCalculationContext,
                $returnOrder->getShippingCosts() ? new PriceCollection([$returnOrder->getShippingCosts()]) : null,
            );

            $updatePayloads[] = [
                'id' => $returnOrder->getId(),
                'price' => $cartPrice,
                // All return order line item prices are updates within the same operation
                'lineItems' => $returnOrderLineItemUpdatePayloads,
                'refund' => [
                    'id' => $returnOrder->getRefund()->getId(),
                    'moneyValue' => [
                        'value' => $cartPrice->getTotalPrice(),
                        'currency' => [
                            'isoCode' => $returnOrder->getRefund()->getCurrencyIsoCode(),
                        ],
                    ],
                ],
            ];
        }

        $this->entityManager->update(ReturnOrderDefinition::class, $updatePayloads, $context);
    }

    /**
     * @param string $taxState See Shopware\Core\Checkout\Cart\Price\Struct\CartPrice
     */
    public static function createEmptyCartPrice(string $taxState): CartPrice
    {
        return new CartPrice(
            0.0,
            0.0,
            0.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            $taxState,
            0.0,
        );
    }

    public static function createEmptyCalculatedPrice(): CalculatedPrice
    {
        return new CalculatedPrice(
            0.0,
            0.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        );
    }
}
