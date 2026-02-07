<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Order\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PickwareErpPickwareOrderLineItemEntity extends Entity
{
    use EntityIdTrait;

    protected int $externallyFulfilledQuantity;
    protected string $orderLineItemId;
    protected string $orderLineItemVersionId;
    protected ?OrderLineItemEntity $orderLineItem = null;

    public function getExternallyFulfilledQuantity(): int
    {
        return $this->externallyFulfilledQuantity;
    }

    public function setExternallyFulfilledQuantity(int $externallyFulfilledQuantity): void
    {
        $this->externallyFulfilledQuantity = $externallyFulfilledQuantity;
    }

    public function getOrderLineItemId(): string
    {
        return $this->orderLineItemId;
    }

    public function setOrderLineItemId(string $orderLineItemId): void
    {
        if ($this->orderLineItem && $this->orderLineItem->getId() !== $this->orderLineItemId) {
            $this->orderLineItem = null;
        }
        $this->orderLineItemId = $orderLineItemId;
    }

    public function getOrderLineItemVersionId(): string
    {
        return $this->orderLineItemVersionId;
    }

    public function setOrderLineItemVersionId(string $orderLineItemVersionId): void
    {
        if ($this->orderLineItem && $this->orderLineItem->getVersionId() !== $this->orderLineItemVersionId) {
            $this->orderLineItem = null;
        }
        $this->orderLineItemVersionId = $orderLineItemVersionId;
    }

    public function getOrderLineItem(): OrderLineItemEntity
    {
        if (!$this->orderLineItem) {
            throw new AssociationNotLoadedException('orderLineItem', $this);
        }

        return $this->orderLineItem;
    }

    public function setOrderLineItem(OrderLineItemEntity $orderLineItem): void
    {
        $this->orderLineItem = $orderLineItem;
        $this->orderLineItemId = $orderLineItem->getId();
    }

    public static function getInternallyFulfilledQuantityFromOderLineItem(OrderLineItemEntity $orderLineItem): int
    {
        return $orderLineItem->getQuantity() - ($orderLineItem->getExtension('pickwareErpPickwareOrderLineItem')?->getExternallyFulfilledQuantity() ?? 0);
    }
}
