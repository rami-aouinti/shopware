<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockingProcess\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Pickware\PickwareWms\Device\Model\DeviceEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\User\UserEntity;

class StockingProcessEntity extends Entity
{
    use EntityIdTrait;

    protected string $number;
    protected string $stateId;
    protected ?StateMachineStateEntity $state = null;
    protected string $warehouseId;
    protected ?WarehouseEntity $warehouse = null;
    protected ?string $userId;
    protected ?UserEntity $user = null;
    protected ?string $deviceId;
    protected ?DeviceEntity $device = null;
    protected ?StockingProcessSourceCollection $sources = null;
    protected ?StockingProcessLineItemCollection $lineItems = null;

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }

    public function getStateId(): string
    {
        return $this->stateId;
    }

    public function setStateId(string $stateId): void
    {
        if ($this->state?->getId() !== $stateId) {
            $this->state = null;
        }
        $this->stateId = $stateId;
    }

    public function getState(): StateMachineStateEntity
    {
        if (!$this->state) {
            throw new AssociationNotLoadedException('state', $this);
        }

        return $this->state;
    }

    public function setState(StateMachineStateEntity $state): void
    {
        $this->stateId = $state->getId();
        $this->state = $state;
    }

    public function getWarehouseId(): string
    {
        return $this->warehouseId;
    }

    public function setWarehouseId(string $warehouseId): void
    {
        if ($this->warehouse?->getId() !== $warehouseId) {
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

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        if ($this->user?->getId() !== $userId) {
            $this->user = null;
        }
        $this->userId = $userId;
    }

    public function getUser(): ?UserEntity
    {
        if (!$this->user && $this->userId) {
            throw new AssociationNotLoadedException('user', $this);
        }

        return $this->user;
    }

    public function setUser(?UserEntity $user): void
    {
        $this->userId = $user?->getId();
        $this->user = $user;
    }

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function setDeviceId(?string $deviceId): void
    {
        if ($this->device?->getId() !== $deviceId) {
            $this->device = null;
        }
        $this->deviceId = $deviceId;
    }

    public function getDevice(): ?DeviceEntity
    {
        if (!$this->device && $this->deviceId) {
            throw new AssociationNotLoadedException('device', $this);
        }

        return $this->device;
    }

    public function setDevice(?DeviceEntity $device): void
    {
        $this->deviceId = $device?->getId();
        $this->device = $device;
    }

    public function getSources(): StockingProcessSourceCollection
    {
        if (!$this->sources) {
            throw new AssociationNotLoadedException('sources', $this);
        }

        return $this->sources;
    }

    public function setSources(StockingProcessSourceCollection $sources): void
    {
        $this->sources = $sources;
    }

    public function getLineItems(): StockingProcessLineItemCollection
    {
        if (!$this->lineItems) {
            throw new AssociationNotLoadedException('lineItems', $this);
        }

        return $this->lineItems;
    }

    public function setLineItems(StockingProcessLineItemCollection $lineItems): void
    {
        $this->lineItems = $lineItems;
    }
}
