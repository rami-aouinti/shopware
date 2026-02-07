<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Statistic\Model;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseSnapshotGenerator;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Pickware\PickwareWms\Device\Model\DeviceEntity;
use Pickware\PickwareWms\Device\Model\DeviceSnapshotGenerator;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessEntity;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessSnapshotGenerator;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileEntity;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileSnapshotGenerator;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\OrderSnapshotGenerator;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\SalesChannelSnapshotGenerator;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\UserSnapshotGenerator;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\User\UserEntity;

/**
 * @phpstan-import-type OrderSnapshot from OrderSnapshotGenerator
 * @phpstan-import-type UserSnapshot from UserSnapshotGenerator
 * @phpstan-import-type PickingProcessSnapshot from PickingProcessSnapshotGenerator
 * @phpstan-import-type WarehouseSnapshot from WarehouseSnapshotGenerator
 * @phpstan-import-type PickingProfileSnapshot from PickingProfileSnapshotGenerator
 * @phpstan-import-type DeviceSnapshot from DeviceSnapshotGenerator
 * @phpstan-import-type SalesChannelSnapshot from SalesChannelSnapshotGenerator
 */
class DeliveryLifecycleEventEntity extends Entity
{
    use EntityIdTrait;

    protected string $deliveryReferenceId;
    protected ?DeliveryEntity $delivery = null;
    protected DeliveryLifecycleEventType $eventTechnicalName;
    protected string $userReferenceId;
    protected ?UserEntity $user = null;

    /**
     * @var UserSnapshot
     */
    protected array $userSnapshot;

    protected string $orderReferenceId;
    protected string $orderVersionId;
    protected ?OrderEntity $order = null;

    /**
     * @var OrderSnapshot
     */
    protected array $orderSnapshot;

    protected string $salesChannelReferenceId;
    protected ?SalesChannelEntity $salesChannel = null;

    /**
     * @var SalesChannelSnapshot
     */
    protected array $salesChannelSnapshot;

    protected string $pickingProcessReferenceId;
    protected ?PickingProcessEntity $pickingProcess = null;

    /**
     * @var PickingProcessSnapshot
     */
    protected array $pickingProcessSnapshot;

    protected string $warehouseReferenceId;
    protected ?WarehouseEntity $warehouse = null;

    /**
     * @var WarehouseSnapshot
     */
    protected array $warehouseSnapshot;

    protected string $pickingMode;
    protected ?string $pickingProfileReferenceId = null;
    protected ?PickingProfileEntity $pickingProfile = null;

    /**
     * @var ?PickingProfileSnapshot
     */
    protected ?array $pickingProfileSnapshot = null;

    protected ?string $deviceReferenceId = null;
    protected ?DeviceEntity $device = null;

    /**
     * @var ?DeviceSnapshot
     */
    protected ?array $deviceSnapshot = null;

    protected DateTimeInterface $eventCreatedAt;
    protected DateTimeInterface $eventCreatedAtDay;
    protected int $eventCreatedAtHour;
    protected int $eventCreatedAtWeekday;
    protected string $eventCreatedAtLocaltime;
    protected string $eventCreatedAtLocaltimeTimezone;
    protected int $eventCreatedAtLocaltimeHour;
    protected int $eventCreatedAtLocaltimeWeekday;
    protected ?DeliveryLifecycleEventUserRoleCollection $userRoles = null;

    public function getDeliveryReferenceId(): string
    {
        return $this->deliveryReferenceId;
    }

    public function setDeliveryReferenceId(string $deliveryReferenceId): void
    {
        if ($this->delivery && $this->delivery->getId() !== $deliveryReferenceId) {
            $this->delivery = null;
        }
        $this->deliveryReferenceId = $deliveryReferenceId;
    }

    public function getDelivery(): ?DeliveryEntity
    {
        return $this->delivery;
    }

    public function setDelivery(DeliveryEntity $delivery): void
    {
        $this->delivery = $delivery;
        $this->deliveryReferenceId = $delivery->getId();
    }

    public function getEventTechnicalName(): DeliveryLifecycleEventType
    {
        return $this->eventTechnicalName;
    }

    public function setEventTechnicalName(DeliveryLifecycleEventType $eventTechnicalName): void
    {
        $this->eventTechnicalName = $eventTechnicalName;
    }

    public function getUserReferenceId(): string
    {
        return $this->userReferenceId;
    }

    public function setUserReferenceId(string $userReferenceId): void
    {
        if ($this->user && $this->user->getId() !== $userReferenceId) {
            $this->user = null;
        }
        $this->userReferenceId = $userReferenceId;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(?UserEntity $user): void
    {
        $this->user = $user;
        $this->userReferenceId = $user?->getId();
    }

    /**
     * @return UserSnapshot
     */
    public function getUserSnapshot(): array
    {
        return $this->userSnapshot;
    }

    /**
     * @param UserSnapshot $userSnapshot
     */
    public function setUserSnapshot(array $userSnapshot): void
    {
        $this->userSnapshot = $userSnapshot;
    }

    public function getOrderReferenceId(): string
    {
        return $this->orderReferenceId;
    }

    public function setOrderReferenceId(string $orderReferenceId): void
    {
        if ($this->order && $this->order->getId() !== $orderReferenceId) {
            $this->order = null;
        }
        $this->orderReferenceId = $orderReferenceId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
        $this->orderReferenceId = $order->getId();
        $this->orderVersionId = $order->getVersionId();
    }

    /**
     * @return OrderSnapshot
     */
    public function getOrderSnapshot(): array
    {
        return $this->orderSnapshot;
    }

    /**
     * @param OrderSnapshot $orderSnapshot
     */
    public function setOrderSnapshot(array $orderSnapshot): void
    {
        $this->orderSnapshot = $orderSnapshot;
    }

    public function getSalesChannelReferenceId(): string
    {
        return $this->salesChannelReferenceId;
    }

    public function setSalesChannelReferenceId(string $salesChannelReferenceId): void
    {
        if ($this->salesChannel && $this->salesChannel->getId() !== $salesChannelReferenceId) {
            $this->salesChannel = null;
        }
        $this->salesChannelReferenceId = $salesChannelReferenceId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
        $this->salesChannelReferenceId = $salesChannel->getId();
    }

    /**
     * @return SalesChannelSnapshot
     */
    public function getSalesChannelSnapshot(): array
    {
        return $this->salesChannelSnapshot;
    }

    /**
     * @param SalesChannelSnapshot $salesChannelSnapshot
     */
    public function setSalesChannelSnapshot(array $salesChannelSnapshot): void
    {
        $this->salesChannelSnapshot = $salesChannelSnapshot;
    }

    public function getPickingProcessReferenceId(): string
    {
        return $this->pickingProcessReferenceId;
    }

    public function setPickingProcessReferenceId(string $pickingProcessReferenceId): void
    {
        if ($this->pickingProcess && $this->pickingProcess->getId() !== $pickingProcessReferenceId) {
            $this->pickingProcess = null;
        }
        $this->pickingProcessReferenceId = $pickingProcessReferenceId;
    }

    public function getPickingProcess(): ?PickingProcessEntity
    {
        return $this->pickingProcess;
    }

    public function setPickingProcess(PickingProcessEntity $pickingProcess): void
    {
        $this->pickingProcess = $pickingProcess;
        $this->pickingProcessReferenceId = $pickingProcess->getId();
    }

    /**
     * @return PickingProcessSnapshot
     */
    public function getPickingProcessSnapshot(): array
    {
        return $this->pickingProcessSnapshot;
    }

    /**
     * @param PickingProcessSnapshot $pickingProcessSnapshot
     */
    public function setPickingProcessSnapshot(array $pickingProcessSnapshot): void
    {
        $this->pickingProcessSnapshot = $pickingProcessSnapshot;
    }

    public function getWarehouseReferenceId(): string
    {
        return $this->warehouseReferenceId;
    }

    public function setWarehouseReferenceId(string $warehouseReferenceId): void
    {
        if ($this->warehouse && $this->warehouse->getId() !== $warehouseReferenceId) {
            $this->warehouse = null;
        }
        $this->warehouseReferenceId = $warehouseReferenceId;
    }

    public function getWarehouse(): ?WarehouseEntity
    {
        return $this->warehouse;
    }

    public function setWarehouse(WarehouseEntity $warehouse): void
    {
        $this->warehouse = $warehouse;
        $this->warehouseReferenceId = $warehouse->getId();
    }

    /**
     * @return WarehouseSnapshot
     */
    public function getWarehouseSnapshot(): array
    {
        return $this->warehouseSnapshot;
    }

    /**
     * @param WarehouseSnapshot $warehouseSnapshot
     */
    public function setWarehouseSnapshot(array $warehouseSnapshot): void
    {
        $this->warehouseSnapshot = $warehouseSnapshot;
    }

    public function getPickingMode(): string
    {
        return $this->pickingMode;
    }

    public function setPickingMode(string $pickingMode): void
    {
        $this->pickingMode = $pickingMode;
    }

    public function getPickingProfileReferenceId(): ?string
    {
        return $this->pickingProfileReferenceId;
    }

    public function setPickingProfileReferenceId(?string $pickingProfileReferenceId): void
    {
        if ($this->pickingProfile && $this->pickingProfile->getId() !== $pickingProfileReferenceId) {
            $this->pickingProfile = null;
        }
        $this->pickingProfileReferenceId = $pickingProfileReferenceId;
    }

    public function getPickingProfile(): ?PickingProfileEntity
    {
        return $this->pickingProfile;
    }

    public function setPickingProfile(PickingProfileEntity $pickingProfile): void
    {
        $this->pickingProfile = $pickingProfile;
        $this->pickingProfileReferenceId = $pickingProfile->getId();
    }

    /**
     * @return ?PickingProfileSnapshot
     */
    public function getPickingProfileSnapshot(): ?array
    {
        return $this->pickingProfileSnapshot;
    }

    /**
     * @param ?PickingProfileSnapshot $pickingProfileSnapshot
     */
    public function setPickingProfileSnapshot(?array $pickingProfileSnapshot): void
    {
        $this->pickingProfileSnapshot = $pickingProfileSnapshot;
    }

    public function getDeviceReferenceId(): ?string
    {
        return $this->deviceReferenceId;
    }

    public function setDeviceReferenceId(?string $deviceReferenceId): void
    {
        if ($this->device && $this->device->getId() !== $deviceReferenceId) {
            $this->device = null;
        }
        $this->deviceReferenceId = $deviceReferenceId;
    }

    public function getDevice(): ?DeviceEntity
    {
        return $this->device;
    }

    public function setDevice(DeviceEntity $device): void
    {
        $this->deviceReferenceId = $device->getId();
        $this->device = $device;
    }

    /**
     * @return ?DeviceSnapshot
     */
    public function getDeviceSnapshot(): ?array
    {
        return $this->deviceSnapshot;
    }

    /**
     * @param ?DeviceSnapshot $deviceSnapshot
     */
    public function setDeviceSnapshot(?array $deviceSnapshot): void
    {
        $this->deviceSnapshot = $deviceSnapshot;
    }

    public function getEventCreatedAt(): DateTimeInterface
    {
        return $this->eventCreatedAt;
    }

    public function setEventCreatedAt(DateTimeInterface $eventCreatedAt): void
    {
        $this->eventCreatedAt = $eventCreatedAt;
    }

    public function getEventCreatedAtDay(): DateTimeInterface
    {
        return $this->eventCreatedAtDay;
    }

    public function getEventCreatedAtHour(): int
    {
        return $this->eventCreatedAtHour;
    }

    public function getEventCreatedAtWeekday(): int
    {
        return $this->eventCreatedAtWeekday;
    }

    public function getEventCreatedAtLocaltime(): DateTimeImmutable
    {
        return new DateTimeImmutable(
            $this->eventCreatedAtLocaltime,
            new DateTimeZone($this->eventCreatedAtLocaltimeTimezone),
        );
    }

    public function setEventCreatedAtLocaltime(DateTimeImmutable $eventCreatedAtLocaltime): void
    {
        $this->eventCreatedAtLocaltime = $eventCreatedAtLocaltime->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->eventCreatedAtLocaltimeTimezone = $eventCreatedAtLocaltime->getTimezone()->getName();
    }

    public function getEventCreatedAtLocaltimeHour(): int
    {
        return $this->eventCreatedAtLocaltimeHour;
    }

    public function getEventCreatedAtLocaltimeWeekday(): int
    {
        return $this->eventCreatedAtLocaltimeWeekday;
    }

    public function getUserRoles(): DeliveryLifecycleEventUserRoleCollection
    {
        if (!$this->userRoles) {
            throw new AssociationNotLoadedException('userRoles', $this);
        }

        return $this->userRoles;
    }

    public function setUserRoles(DeliveryLifecycleEventUserRoleCollection $userRoles): void
    {
        $this->userRoles = $userRoles;
    }
}
