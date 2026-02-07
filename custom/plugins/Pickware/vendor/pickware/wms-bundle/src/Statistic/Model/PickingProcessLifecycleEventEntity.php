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
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseSnapshotGenerator;
use Pickware\PickwareWms\Device\Model\DeviceEntity;
use Pickware\PickwareWms\Device\Model\DeviceSnapshotGenerator;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessEntity;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessSnapshotGenerator;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileEntity;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileSnapshotGenerator;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\UserSnapshotGenerator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\User\UserEntity;

/**
 *  This event is used to calculate statistics over time. References to other entities are not foreign keys, since we
 *  want to still be able to group by the related entity, even If it is deleted in the meantime.
 *  To this end, the return values of the getters for associations are not validated, because they may be null when the
 *  related entity is no longer present.
 *
 * @phpstan-import-type DeviceSnapshot from DeviceSnapshotGenerator
 * @phpstan-import-type PickingProcessSnapshot from PickingProcessSnapshotGenerator
 * @phpstan-import-type UserSnapshot from UserSnapshotGenerator
 * @phpstan-import-type WarehouseSnapshot from WarehouseSnapshotGenerator
 * @phpstan-import-type PickingProfileSnapshot from PickingProfileSnapshotGenerator
 */
class PickingProcessLifecycleEventEntity extends Entity
{
    use EntityIdTrait;

    protected PickingProcessLifecycleEventType $eventTechnicalName;
    protected string $pickingProcessReferenceId;
    protected ?PickingProcessEntity $pickingProcess = null;

    /**
     * @var PickingProcessSnapshot
     */
    protected array $pickingProcessSnapshot;

    protected ?string $userReferenceId;
    protected ?UserEntity $user = null;

    /**
     * @var ?UserSnapshot
     */
    protected ?array $userSnapshot;

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
    protected ?array $deviceSnapshot;

    protected DateTimeInterface $eventCreatedAt;
    protected DateTimeInterface $eventCreatedAtDay;
    protected int $eventCreatedAtHour;
    protected int $eventCreatedAtWeekday;
    protected string $eventCreatedAtLocaltime;
    protected string $eventCreatedAtLocaltimeTimezone;
    protected int $eventCreatedAtLocaltimeHour;
    protected int $eventCreatedAtLocaltimeWeekday;
    protected ?PickingProcessLifecycleEventUserRoleCollection $userRoles = null;

    public function getEventTechnicalName(): PickingProcessLifecycleEventType
    {
        return $this->eventTechnicalName;
    }

    public function setEventTechnicalName(PickingProcessLifecycleEventType $eventTechnicalName): void
    {
        $this->eventTechnicalName = $eventTechnicalName;
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

    public function getUserReferenceId(): ?string
    {
        return $this->userReferenceId;
    }

    public function setUserReferenceId(?string $userReferenceId): void
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
     * @return ?UserSnapshot
     */
    public function getUserSnapshot(): ?array
    {
        return $this->userSnapshot;
    }

    /**
     * @param ?UserSnapshot $userSnapshot
     */
    public function setUserSnapshot(?array $userSnapshot): void
    {
        $this->userSnapshot = $userSnapshot;
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

    public function getUserRoles(): PickingProcessLifecycleEventUserRoleCollection
    {
        return $this->userRoles;
    }

    public function setUserRoles(PickingProcessLifecycleEventUserRoleCollection $userRoles): void
    {
        $this->userRoles = $userRoles;
    }
}
