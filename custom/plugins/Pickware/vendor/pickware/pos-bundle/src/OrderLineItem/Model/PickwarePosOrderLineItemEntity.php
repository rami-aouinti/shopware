<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\OrderLineItem\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PickwarePosOrderLineItemEntity extends Entity
{
    use EntityIdTrait;

    protected string $orderLineItemId;
    protected string $orderLineItemVersionId;
    protected ?OrderLineItemEntity $orderLineItem = null;
    protected float $originalPrice;

    public function getOrderLineItemId(): string
    {
        return $this->orderLineItemId;
    }

    public function setOrderLineItemId(string $orderLineItemId): void
    {
        if ($this->orderLineItem?->getId() !== $orderLineItemId) {
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
        if ($this->orderLineItem?->getVersionId() !== $orderLineItemVersionId) {
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
        $this->orderLineItemId = $orderLineItem->getId();
        $this->orderLineItem = $orderLineItem;
    }

    public function getOriginalPrice(): float
    {
        return $this->originalPrice;
    }

    public function setOriginalPrice(float $originalPrice): void
    {
        $this->originalPrice = $originalPrice;
    }
}
