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
use Pickware\PickwareErpStarter\Warehouse\BinLocationSnapshotGenerator;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseSnapshotGenerator;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessEntity;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessSnapshotGenerator;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileEntity;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileSnapshotGenerator;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\OrderSnapshotGenerator;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\ProductSnapshotGenerator;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\UserSnapshotGenerator;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\User\UserEntity;

/**
 * This entity is used for complete statistics over time, so references to other entities are not foreign keys, as these
 * entries should remain even if, for example, a product is deleted.  If the entity is no longer present, the data will
 * be fetched from the snapshot. For this reason, the return values of the relations are not validated in this entity,
 * because they may be null when the related entity is no longer present.
 *
 * @phpstan-import-type ProductSnapshot from ProductSnapshotGenerator
 * @phpstan-import-type UserSnapshot from UserSnapshotGenerator
 * @phpstan-import-type WarehouseSnapshot from WarehouseSnapshotGenerator
 * @phpstan-import-type BinLocationSnapshot from BinLocationSnapshotGenerator
 * @phpstan-import-type PickingProcessSnapshot from PickingProcessSnapshotGenerator
 * @phpstan-import-type PickingProfileSnapshot from PickingProfileSnapshotGenerator
 * @phpstan-import-type OrderSnapshot from OrderSnapshotGenerator
 */
class PickEventEntity extends Entity
{
    use EntityIdTrait;

    protected string $productReferenceId;

    /**
     * @var ProductSnapshot
     */
    protected array $productSnapshot;

    protected ?ProductEntity $product = null;
    protected ?float $productWeight = null;
    protected string $userReferenceId;

    /**
     * @var UserSnapshot
     */
    protected array $userSnapshot;

    protected ?UserEntity $user = null;
    protected string $warehouseReferenceId;

    /**
     * @var WarehouseSnapshot
     */
    protected array $warehouseSnapshot;

    protected ?WarehouseEntity $warehouse = null;
    protected ?string $binLocationReferenceId;

    /**
     * @var ?BinLocationSnapshot
     */
    protected ?array $binLocationSnapshot;

    protected ?BinLocationEntity $binLocation = null;
    protected string $pickingProcessReferenceId;

    /**
     * @var PickingProcessSnapshot
     */
    protected array $pickingProcessSnapshot;

    protected ?PickingProcessEntity $pickingProcess = null;
    protected string $pickingMode;
    protected ?string $pickingProfileReferenceId = null;

    /**
     * @var ?PickingProfileSnapshot
     */
    protected ?array $pickingProfileSnapshot = null;

    protected ?PickingProfileEntity $pickingProfile = null;
    protected int $pickedQuantity;
    protected DateTimeInterface $pickCreatedAt;
    protected DateTimeInterface $pickCreatedAtDay;
    protected int $pickCreatedAtHour;
    protected int $pickCreatedAtWeekday;
    protected string $pickCreatedAtLocaltime;
    protected string $pickCreatedAtLocaltimeTimezone;
    protected int $pickCreatedAtLocaltimeHour;
    protected int $pickCreatedAtLocaltimeWeekday;
    protected ?PickEventUserRoleCollection $userRoles = null;

    public function getProductReferenceId(): string
    {
        return $this->productReferenceId;
    }

    public function setProductReferenceId(string $productReferenceId): void
    {
        if ($this->product && $this->product->getId() !== $productReferenceId) {
            $this->product = null;
        }
        $this->productReferenceId = $productReferenceId;
    }

    /**
     * @return ProductSnapshot
     */
    public function getProductSnapshot(): array
    {
        return $this->productSnapshot;
    }

    /**
     * @param ProductSnapshot $productSnapshot
     */
    public function setProductSnapshot(array $productSnapshot): void
    {
        $this->productSnapshot = $productSnapshot;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        if ($product) {
            $this->productReferenceId = $product->getId();
        }
        $this->product = $product;
    }

    public function getProductWeight(): ?float
    {
        return $this->productWeight;
    }

    public function setProductWeight(?float $productWeight): void
    {
        $this->productWeight = $productWeight;
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

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(?UserEntity $user): void
    {
        if ($user) {
            $this->userReferenceId = $user->getId();
        }
        $this->user = $user;
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

    public function getWarehouse(): ?WarehouseEntity
    {
        return $this->warehouse;
    }

    public function setWarehouse(?WarehouseEntity $warehouse): void
    {
        if ($warehouse) {
            $this->warehouseReferenceId = $warehouse->getId();
        }
        $this->warehouse = $warehouse;
    }

    public function getBinLocationReferenceId(): ?string
    {
        return $this->binLocationReferenceId;
    }

    public function setBinLocationReferenceId(?string $binLocationReferenceId): void
    {
        if ($this->binLocation && $this->binLocation->getId() !== $binLocationReferenceId) {
            $this->binLocation = null;
        }
        $this->binLocationReferenceId = $binLocationReferenceId;
    }

    /**
     * @return ?BinLocationSnapshot
     */
    public function getBinLocationSnapshot(): ?array
    {
        return $this->binLocationSnapshot;
    }

    /**
     * @param ?BinLocationSnapshot $binLocationSnapshot
     */
    public function setBinLocationSnapshot(?array $binLocationSnapshot): void
    {
        $this->binLocationSnapshot = $binLocationSnapshot;
    }

    public function getBinLocation(): ?BinLocationEntity
    {
        return $this->binLocation;
    }

    public function setBinLocation(?BinLocationEntity $binLocation): void
    {
        if ($binLocation) {
            $this->binLocationReferenceId = $binLocation->getId();
        }
        $this->binLocation = $binLocation;
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

    public function getPickingProcess(): ?PickingProcessEntity
    {
        return $this->pickingProcess;
    }

    public function setPickingProcess(?PickingProcessEntity $pickingProcess): void
    {
        if ($pickingProcess) {
            $this->pickingProcessReferenceId = $pickingProcess->getId();
        }
        $this->pickingProcess = $pickingProcess;
    }

    public function getPickingMode(): string
    {
        return $this->pickingMode;
    }

    public function setPickingMode(string $pickingMode): void
    {
        $this->pickingMode = $pickingMode;
    }

    public function getPickingProfileReferenceId(): string
    {
        return $this->pickingProfileReferenceId;
    }

    public function setPickingProfileReferenceId(string $pickingProfileReferenceId): void
    {
        if ($this->pickingProfile && $this->pickingProfile->getId() !== $pickingProfileReferenceId) {
            $this->pickingProfile = null;
        }
        $this->pickingProfileReferenceId = $pickingProfileReferenceId;
    }

    /**
     * @return ?PickingProfileSnapshot
     */
    public function getPickingProfileSnapshot(): ?array
    {
        return $this->pickingProfileSnapshot;
    }

    /**
     * @param PickingProfileSnapshot $pickingProfileSnapshot
     */
    public function setPickingProfileSnapshot(array $pickingProfileSnapshot): void
    {
        $this->pickingProfileSnapshot = $pickingProfileSnapshot;
    }

    public function getPickingProfile(): ?PickingProfileEntity
    {
        return $this->pickingProfile;
    }

    public function setPickingProfile(?PickingProfileEntity $pickingProfile): void
    {
        if ($pickingProfile) {
            $this->pickingProfileReferenceId = $pickingProfile->getId();
        }
        $this->pickingProfile = $pickingProfile;
    }

    public function getPickedQuantity(): int
    {
        return $this->pickedQuantity;
    }

    public function setPickedQuantity(int $pickedQuantity): void
    {
        $this->pickedQuantity = $pickedQuantity;
    }

    public function getPickCreatedAt(): DateTimeInterface
    {
        return $this->pickCreatedAt;
    }

    public function setPickCreatedAt(DateTimeInterface $pickCreatedAt): void
    {
        $this->pickCreatedAt = $pickCreatedAt;
    }

    public function getPickCreatedAtDay(): DateTimeInterface
    {
        return $this->pickCreatedAtDay;
    }

    public function getPickCreatedAtHour(): int
    {
        return $this->pickCreatedAtHour;
    }

    public function getPickCreatedAtWeekday(): int
    {
        return $this->pickCreatedAtWeekday;
    }

    public function getPickCreatedAtLocaltime(): DateTimeImmutable
    {
        return new DateTimeImmutable(
            $this->pickCreatedAtLocaltime,
            new DateTimeZone($this->pickCreatedAtLocaltimeTimezone),
        );
    }

    public function setPickCreatedAtLocaltime(DateTimeImmutable $pickCreatedAtLocaltime): void
    {
        $this->pickCreatedAtLocaltime = $pickCreatedAtLocaltime->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->pickCreatedAtLocaltimeTimezone = $pickCreatedAtLocaltime->getTimezone()->getName();
    }

    public function getPickCreatedAtLocaltimeHour(): int
    {
        return $this->pickCreatedAtLocaltimeHour;
    }

    public function getPickCreatedAtLocaltimeWeekday(): int
    {
        return $this->pickCreatedAtLocaltimeWeekday;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUserRoles(): PickEventUserRoleCollection
    {
        return $this->userRoles;
    }

    public function setUserRoles(PickEventUserRoleCollection $userRoles): void
    {
        $this->userRoles = $userRoles;
    }
}
