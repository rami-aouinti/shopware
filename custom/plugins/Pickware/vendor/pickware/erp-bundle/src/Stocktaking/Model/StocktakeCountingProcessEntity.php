<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\User\UserEntity;

class StocktakeCountingProcessEntity extends Entity
{
    use EntityIdTrait;

    protected string $number;
    protected string $stocktakeId;
    protected ?StocktakeEntity $stocktake = null;
    protected ?StocktakeCountingProcessItemCollection $items = null;
    protected ?string $binLocationId = null;
    protected ?BinLocationEntity $binLocation = null;
    protected ?array $binLocationSnapshot;
    protected ?string $userId;
    protected ?UserEntity $user = null;
    protected array $userSnapshot;

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }

    public function getStocktakeId(): string
    {
        return $this->stocktakeId;
    }

    public function setStocktakeId(string $stocktakeId): void
    {
        if ($this->stocktake && $this->stocktake->getId() !== $stocktakeId) {
            $this->stocktake = null;
        }
        $this->stocktakeId = $stocktakeId;
    }

    public function getStocktake(): StocktakeEntity
    {
        if (!$this->stocktake) {
            throw new AssociationNotLoadedException('stocktake', $this);
        }

        return $this->stocktake;
    }

    public function setStocktake(StocktakeEntity $stocktake): void
    {
        $this->stocktake = $stocktake;
        $this->stocktakeId = $stocktake->getId();
    }

    public function getItems(): StocktakeCountingProcessItemCollection
    {
        if (!$this->items) {
            throw new AssociationNotLoadedException('items', $this);
        }

        return $this->items;
    }

    public function setItems(StocktakeCountingProcessItemCollection $items): void
    {
        $this->items = $items;
    }

    public function getBinLocationId(): ?string
    {
        return $this->binLocationId;
    }

    public function setBinLocationId(?string $binLocationId): void
    {
        if ($this->binLocation && $this->binLocation->getId() !== $binLocationId) {
            $this->binLocation = null;
        }
        $this->binLocationId = $binLocationId;
    }

    public function getBinLocation(): ?BinLocationEntity
    {
        if (!$this->binLocation && $this->binLocationId) {
            throw new AssociationNotLoadedException('binLocation', $this);
        }

        return $this->binLocation;
    }

    public function setBinLocation(?BinLocationEntity $binLocation): void
    {
        if ($binLocation) {
            $this->binLocationId = $binLocation->getId();
        }
        $this->binLocation = $binLocation;
    }

    public function getBinLocationSnapshot(): ?array
    {
        return $this->binLocationSnapshot;
    }

    public function setBinLocationSnapshot(?array $binLocationSnapshot): void
    {
        $this->binLocationSnapshot = $binLocationSnapshot;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        if ($this->user && $this->user->getId() !== $userId) {
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
        if ($user) {
            $this->userId = $user->getId();
        }
        $this->user = $user;
    }

    public function getUserSnapshot(): array
    {
        return $this->userSnapshot;
    }

    public function setUserSnapshot(array $userSnapshot): void
    {
        $this->userSnapshot = $userSnapshot;
    }
}
