<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SingleItemOrder\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class SingleItemOrderEntity extends Entity
{
    use EntityIdTrait;

    protected string $orderId;
    protected string $orderVersionId;
    protected ?OrderEntity $order = null;
    protected bool $isSingleItemOrder;
    protected bool $isOpenSingleItemOrder;

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        if ($this->order && $this->order->getId() !== $orderId) {
            $this->order = null;
        }
        $this->orderId = $orderId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(string $orderVersionId): void
    {
        if ($this->order && $this->order->getVersionId() !== $orderVersionId) {
            $this->order = null;
        }
        $this->orderVersionId = $orderVersionId;
    }

    public function getOrder(): OrderEntity
    {
        if (!$this->order) {
            throw new AssociationNotLoadedException('order', $this);
        }

        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->orderId = $order->getId();
        $this->orderVersionId = $order->getVersionId();
        $this->order = $order;
    }

    public function getIsSingleItemOrder(): bool
    {
        return $this->isSingleItemOrder;
    }

    public function setIsSingleItemOrder(bool $isSingleItemOrder): void
    {
        $this->isSingleItemOrder = $isSingleItemOrder;
    }

    public function getIsOpenSingleItemOrder(): bool
    {
        return $this->isOpenSingleItemOrder;
    }

    public function setIsOpenSingleItemOrder(bool $isOpenSingleItemOrder): void
    {
        $this->isOpenSingleItemOrder = $isOpenSingleItemOrder;
    }
}
