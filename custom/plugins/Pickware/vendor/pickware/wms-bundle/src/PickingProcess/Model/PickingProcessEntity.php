<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Pickware\PickwareWms\Delivery\Model\DeliveryCollection;
use Pickware\PickwareWms\Device\Model\DeviceEntity;
use Pickware\PickwareWms\ShippingProcess\Model\ShippingProcessEntity;
use Pickware\PickwareWms\Statistic\Model\PickEventCollection;
use Pickware\PickwareWms\Statistic\Model\PickingProcessLifecycleEventCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\User\UserEntity;

class PickingProcessEntity extends Entity
{
    use EntityIdTrait;

    protected string $number;
    protected ?string $userId;
    protected ?UserEntity $user = null;
    protected ?string $deviceId;
    protected ?DeviceEntity $device = null;
    protected string $warehouseId;
    protected ?WarehouseEntity $warehouse = null;
    protected ?string $preCollectingStockContainerId;
    protected ?StockContainerEntity $preCollectingStockContainer = null;
    protected string $stateId;
    protected ?StateMachineStateEntity $state = null;
    protected ?PickingProcessReservedItemCollection $reservedItems = null;
    protected ?DeliveryCollection $deliveries = null;
    protected string $pickingMode;
    protected ?string $shippingProcessId = null;
    protected ?ShippingProcessEntity $shippingProcess = null;
    protected ?PickingProcessLifecycleEventCollection $lifecycleEvents = null;
    protected ?PickEventCollection $pickEvents = null;

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        if ($userId && $this->user && $this->user->getId() !== $userId) {
            $this->user = null;
        }
        $this->userId = $userId;
    }

    public function getUser(): ?UserEntity
    {
        if ($this->userId && !$this->user) {
            throw new AssociationNotLoadedException('user', $this);
        }

        return $this->user;
    }

    public function setUser(?UserEntity $user): void
    {
        $this->userId = $user ? $user->getId() : null;
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
        $this->warehouse = $warehouse;
        $this->warehouseId = $warehouse->getId();
    }

    public function getStateId(): string
    {
        return $this->stateId;
    }

    public function setStateId(string $stateId): void
    {
        if ($this->state && $this->state->getId() !== $stateId) {
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

    public function getPreCollectingStockContainerId(): ?string
    {
        return $this->preCollectingStockContainerId;
    }

    public function setPreCollectingStockContainerId(?string $preCollectingStockContainerId): void
    {
        if (
            $this->preCollectingStockContainer
            && $this->preCollectingStockContainer->getId() !== $preCollectingStockContainerId
        ) {
            $this->preCollectingStockContainer = null;
        }
        $this->preCollectingStockContainerId = $preCollectingStockContainerId;
    }

    public function getPreCollectingStockContainer(): ?StockContainerEntity
    {
        if (!$this->preCollectingStockContainer && $this->preCollectingStockContainerId) {
            throw new AssociationNotLoadedException('preCollectingStockContainer', $this);
        }

        return $this->preCollectingStockContainer;
    }

    public function setPreCollectingStockContainer(?StockContainerEntity $preCollectingStockContainer): void
    {
        if ($preCollectingStockContainer) {
            $this->preCollectingStockContainerId = $preCollectingStockContainer->getId();
        }
        $this->preCollectingStockContainer = $preCollectingStockContainer;
    }

    public function getReservedItems(): PickingProcessReservedItemCollection
    {
        if (!$this->reservedItems) {
            throw new AssociationNotLoadedException('reservedItems', $this);
        }

        return $this->reservedItems;
    }

    public function setReservedItems(?PickingProcessReservedItemCollection $reservedItems): void
    {
        $this->reservedItems = $reservedItems;
    }

    public function getDeliveries(): DeliveryCollection
    {
        if (!$this->deliveries) {
            throw new AssociationNotLoadedException('deliveries', $this);
        }

        return $this->deliveries;
    }

    public function setDeliveries(?DeliveryCollection $deliveries): void
    {
        $this->deliveries = $deliveries;
    }

    public function setPickingMode(string $pickingMode): void
    {
        $this->pickingMode = $pickingMode;
    }

    public function getPickingMode(): string
    {
        return $this->pickingMode;
    }

    public function getShippingProcessId(): ?string
    {
        return $this->shippingProcessId;
    }

    public function setShippingProcessId(?string $shippingProcessId): void
    {
        if ($this->shippingProcess && $this->shippingProcess->getId() !== $shippingProcessId) {
            $this->shippingProcess = null;
        }
        $this->shippingProcessId = $shippingProcessId;
    }

    public function getShippingProcess(): ?ShippingProcessEntity
    {
        if (!$this->shippingProcess && $this->shippingProcessId) {
            throw new AssociationNotLoadedException('shippingProcess', $this);
        }

        return $this->shippingProcess;
    }

    public function setShippingProcess(?ShippingProcessEntity $shippingProcess): void
    {
        $this->shippingProcess = $shippingProcess;
        $this->shippingProcessId = $shippingProcess?->getId();
    }

    public function getLifecycleEvents(): ?PickingProcessLifecycleEventCollection
    {
        if (!$this->lifecycleEvents) {
            throw new AssociationNotLoadedException('lifecycleEvents', $this);
        }

        return $this->lifecycleEvents;
    }

    public function setLifecycleEvents(?PickingProcessLifecycleEventCollection $lifecycleEvents): void
    {
        $this->lifecycleEvents = $lifecycleEvents;
    }

    public function getPickEvents(): ?PickEventCollection
    {
        if (!$this->pickEvents) {
            throw new AssociationNotLoadedException('pickEvents', $this);
        }

        return $this->pickEvents;
    }

    public function setPickEvents(?PickEventCollection $pickEvents): void
    {
        $this->pickEvents = $pickEvents;
    }
}
