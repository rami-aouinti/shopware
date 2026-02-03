<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PriceCalculation;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderCollection;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Framework\Context;

class OrderRecalculationService
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

    public function recalculateSupplierOrders(array $supplierOrderIds, Context $context): void
    {
        if (count($supplierOrderIds) === 0) {
            return;
        }

        /** @var SupplierOrderCollection $supplierOrders */
        $supplierOrders = $this->entityManager->findBy(
            SupplierOrderDefinition::class,
            ['id' => $supplierOrderIds],
            $context,
            ['lineItems'],
        );

        if ($supplierOrders->count() === 0) {
            return;
        }

        $supplierOrderUpdatePayloads = [];
        foreach ($supplierOrders as $supplierOrder) {
            $priceCalculationContext = new PriceCalculationContext(
                $supplierOrder->getTaxStatus(),
                $supplierOrder->getItemRounding(),
                $supplierOrder->getTotalRounding(),
            );

            $supplierOrderLineItemUpdatePayloads = [];
            $lineItemPrices = new PriceCollection();
            foreach ($supplierOrder->getLineItems() as $supplierOrderLineItem) {
                // Recalculate each supplier order line item price
                $newLineItemPrice = $this->priceCalculator->calculateQuantityPrice(
                    $supplierOrderLineItem->getPriceDefinition(),
                    $priceCalculationContext,
                );
                $supplierOrderLineItemUpdatePayloads[] = [
                    'id' => $supplierOrderLineItem->getId(),
                    'price' => $newLineItemPrice,
                ];
                $lineItemPrices->add($newLineItemPrice);
            }

            $supplierOrderUpdatePayloads[] = [
                'id' => $supplierOrder->getId(),
                'price' => $this->cartPriceCalculator->calculateCartPrice($lineItemPrices, $priceCalculationContext),
                // All supplier order line item prices are updates within the same operation
                'lineItems' => $supplierOrderLineItemUpdatePayloads,
            ];
        }

        $this->entityManager->update(SupplierOrderDefinition::class, $supplierOrderUpdatePayloads, $context);
    }
}
