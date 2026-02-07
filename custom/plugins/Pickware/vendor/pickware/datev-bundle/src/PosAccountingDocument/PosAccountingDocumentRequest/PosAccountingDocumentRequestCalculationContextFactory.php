<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PosAccountingDocument\PosAccountingDocumentRequest;

use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\AccountingDocument\AccountingDocumentRequest\AccountingDocumentRequestCalculationContext;
use Pickware\PickwareErpStarter\OrderCalculation\CalculatableOrder;
use Pickware\PickwareErpStarter\OrderCalculation\CalculatableOrderFactory;
use Pickware\PickwareErpStarter\OrderCalculation\CalculatableOrderLineItem;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemEntity;
use Pickware\ShopwareExtensionsBundle\OrderTransaction\OrderTransactionCollectionExtension;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class PosAccountingDocumentRequestCalculationContextFactory
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly CalculatableOrderFactory $calculatableOrderFactory,
    ) {}

    public function createCalculationContextForOrder(
        string $orderId,
        Context $context,
    ): AccountingDocumentRequestCalculationContext {
        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $orderId,
            $context,
            [
                'transactions.stateMachineState',
            ],
        );

        $paymentMethodId = OrderTransactionCollectionExtension::getPrimaryOrderTransaction(
            $order->getTransactions(),
        )->getPaymentMethodId();

        return new AccountingDocumentRequestCalculationContext(
            orderId: $orderId,
            orderNumber: $order->getOrderNumber(),
            calculatableOrder: $this->calculatableOrderFactory->createCalculatableOrderFromOrder(
                orderId: $orderId,
                context: $context,
            ),
            isShopifyOrder: false,
            isPreDiscountFix: false,
            countryIsoCode: null,
            paymentMethodId: $paymentMethodId,
            orderVatIds: null,
            orderCompany: null,
        );
    }

    public function createCalculationContextForReturnOrder(
        string $returnOrderId,
        Context $context,
    ): AccountingDocumentRequestCalculationContext {
        /** @var ReturnOrderEntity $returnOrder */
        $returnOrder = $this->entityManager->getByPrimaryKey(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
            [
                'lineItems',
                'order.transactions.stateMachineState',
            ],
        );
        $order = $returnOrder->getOrder();
        $orderId = $order->getId();

        $paymentMethodId = OrderTransactionCollectionExtension::getPrimaryOrderTransaction(
            $order->getTransactions(),
        )->getPaymentMethodId();

        if (method_exists(CalculatableOrderFactory::class, 'createCalculatableOrderFromReturnOrder')) {
            $calculatableOrder = $this->calculatableOrderFactory->createCalculatableOrderFromReturnOrder(
                returnOrderId: $returnOrderId,
                context: $context,
            );
        } else {
            $calculatableOrder = $this->createCalculatableOrderFromReturnOrderEntity($returnOrder);
        }

        return new AccountingDocumentRequestCalculationContext(
            orderId: $orderId,
            orderNumber: $order->getOrderNumber(),
            calculatableOrder: $calculatableOrder,
            isShopifyOrder: false,
            isPreDiscountFix: false,
            countryIsoCode: null,
            paymentMethodId: $paymentMethodId,
            orderVatIds: null,
            orderCompany: null,
        );
    }

    /**
     * Copied from ERPs OrderCalculation\CalculatableOrderFactory since the function is private, and we can not
     * use the public function as it does not expose a way to determine which CalculatableOrder belongs to which
     * ReturnOrder.
     *
     * @deprecated can be removed once datev requires at least ERP starter 4.15.0
     */
    private function createCalculatableOrderFromReturnOrderEntity(ReturnOrderEntity $returnOrder): CalculatableOrder
    {
        $order = new CalculatableOrder();
        $order->lineItems = array_values($returnOrder->getLineItems()->map(
            fn(ReturnOrderLineItemEntity $returnOrderLineItem) => $this->createOrderLineItemFromReturnOrderLineItemEntity($returnOrderLineItem),
        ));
        $order->price = $returnOrder->getPrice();
        $order->shippingCosts = $returnOrder->getShippingCosts() ?? new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection());

        return $order;
    }

    private function createOrderLineItemFromReturnOrderLineItemEntity(
        ReturnOrderLineItemEntity $returnOrderLineItemEntity,
    ): CalculatableOrderLineItem {
        $orderLineItem = new CalculatableOrderLineItem();
        $orderLineItem->type = $returnOrderLineItemEntity->getType();
        $orderLineItem->label = $returnOrderLineItemEntity->getName();
        $orderLineItem->price = $returnOrderLineItemEntity->getPrice();
        $orderLineItem->quantity = $returnOrderLineItemEntity->getQuantity();
        $orderLineItem->productId = $returnOrderLineItemEntity->getProductId();
        $orderLineItem->productVersionId = $returnOrderLineItemEntity->getProductVersionId();
        if ($returnOrderLineItemEntity->getOrderLineItem()) {
            $orderLineItem->payload = $returnOrderLineItemEntity->getOrderLineItem()->getPayload();
        } else {
            // Minimal backup payload if the order line item does not exist anymore
            $orderLineItem->payload = [
                'productNumber' => $returnOrderLineItemEntity->getProductNumber(),
            ];
        }

        return $orderLineItem;
    }
}
