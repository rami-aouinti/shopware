<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Model;

use LogicException;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemEntity;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderStateMachine;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderEntity;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemEntity;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderStateMachine;
use RuntimeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

enum GoodsReceiptType: string
{
    case Free = 'free';
    case Customer = 'customer';
    case Supplier = 'supplier';

    public function getOriginsFromGoodsReceipt(GoodsReceiptEntity $goodsReceipt): EntityCollection
    {
        return match ($goodsReceipt->getType()) {
            self::Customer => $goodsReceipt->getReturnOrders(),
            self::Supplier => $goodsReceipt->getSupplierOrders(),
            self::Free => throw new RuntimeException('Free goods receipts do not have origins'),
        };
    }

    public function setTypeSpecificLineItemPayload(
        GoodsReceiptEntity $goodsReceipt,
        ReturnOrderLineItemEntity|SupplierOrderLineItemEntity $originLineItem,
        array &$payload,
    ): void {
        switch ($goodsReceipt->getType()) {
            case self::Supplier:
                $payload['supplierOrderId'] = $originLineItem->getSupplierOrderId();
                break;
            case self::Customer:
                $payload['returnOrderId'] = $originLineItem->getReturnOrderId();
                // Since goods receipts to return orders should have no price properties,
                // we don't set one here either
                $payload['priceDefinition'] = null;
                break;
            case self::Free:
                throw new LogicException('The line items of a free goods receipt should never be reassigned');
        }
    }

    public function getGoodsReceiptLineItemOriginPropertyName(): ?string
    {
        return match ($this) {
            self::Customer => 'returnOrder',
            self::Supplier => 'supplierOrder',
            self::Free => null,
        };
    }

    public function isOriginStateFinal(string $stateTechnicalName): bool
    {
        $finalStates = match ($this) {
            self::Supplier => [
                SupplierOrderStateMachine::STATE_DELIVERED,
                SupplierOrderStateMachine::STATE_CANCELLED,
                SupplierOrderStateMachine::STATE_COMPLETED,
            ],
            self::Customer => [
                ReturnOrderStateMachine::STATE_RECEIVED,
                ReturnOrderStateMachine::STATE_CANCELLED,
                ReturnOrderStateMachine::STATE_COMPLETED,
            ],
            self::Free => throw new LogicException('A free goods receipt does not have an origin'),
        };

        return in_array($stateTechnicalName, $finalStates, true);
    }

    public static function fromOriginEntity(ReturnOrderEntity|SupplierOrderEntity $origin): self
    {
        return match ($origin::class) {
            SupplierOrderEntity::class => self::Supplier,
            ReturnOrderEntity::class => self::Customer,
        };
    }

    public function getOriginEntityName(): string
    {
        return match ($this) {
            self::Customer => ReturnOrderDefinition::ENTITY_NAME,
            self::Supplier => SupplierOrderDefinition::ENTITY_NAME,
            self::Free => throw new RuntimeException('A free goods receipt does not have an origin'),
        };
    }

    public function getOriginPartiallyReceivedStateTransitionName(): string
    {
        return match ($this) {
            self::Customer => ReturnOrderStateMachine::TRANSITION_RECEIVE_PARTIALLY,
            self::Supplier => SupplierOrderStateMachine::TRANSITION_DELIVER_PARTIALLY,
            self::Free => throw new RuntimeException('A free goods receipt does not have an origin'),
        };
    }

    public function getOriginReceivedStateTransitionName(): string
    {
        return match ($this) {
            self::Customer => ReturnOrderStateMachine::TRANSITION_RECEIVE,
            self::Supplier => SupplierOrderStateMachine::TRANSITION_DELIVER,
            self::Free => throw new RuntimeException('A free goods receipt does not have an origin'),
        };
    }

    public static function getOriginLineItemIsProductType(ReturnOrderLineItemEntity|SupplierOrderLineItemEntity $originLineItem): bool
    {
        return match ($originLineItem::class) {
            ReturnOrderLineItemEntity::class => $originLineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE && $originLineItem->getProductId() !== null,
            SupplierOrderLineItemEntity::class => $originLineItem->getProductId() !== null,
        };
    }
}
