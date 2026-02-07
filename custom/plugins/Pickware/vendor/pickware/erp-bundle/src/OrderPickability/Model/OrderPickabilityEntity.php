<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderPickability\Model;

use InvalidArgumentException;
use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OrderPickabilityEntity extends Entity
{
    use EntityIdTrait;

    protected string $warehouseId;
    protected ?WarehouseEntity $warehouse = null;
    protected string $orderId;
    protected string $orderVersionId;
    protected ?OrderEntity $order = null;
    protected string $orderPickabilityStatus;

    public function getWarehouseId(): string
    {
        return $this->warehouseId;
    }

    public function setWarehouseId(string $warehouseId): void
    {
        if ($this->warehouse && $this->warehouse->getId() !== $warehouseId) {
            $this->warehouse = null;
        }
        $this->warehouseId = $warehouseId;
    }

    public function getWarehouse(): WarehouseEntity
    {
        if (!$this->warehouse) {
            throw new AssociationNotLoadedException('warehouse', $this);
        }

        return $this->warehouse;
    }

    public function setWarehouse(WarehouseEntity $warehouse): void
    {
        $this->warehouseId = $warehouse->getId();
        $this->warehouse = $warehouse;
    }

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

    public function getOrderVersionId(): ?string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(?string $orderVersionId): void
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
        $this->order = $order;
    }

    public function getOrderPickabilityStatus(): string
    {
        return $this->orderPickabilityStatus;
    }

    public function setOrderPickabilityStatus(string $orderPickabilityStatus): void
    {
        if (!in_array($orderPickabilityStatus, OrderPickabilityDefinition::PICKABILITY_STATES)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid pickability state. Possible values: %s, Given: "%s".',
                implode(', ', OrderPickabilityDefinition::PICKABILITY_STATES),
                $orderPickabilityStatus,
            ));
        }
        $this->orderPickabilityStatus = $orderPickabilityStatus;
    }
}
