<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockMovementProcess\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\User\UserEntity;

class StockMovementProcessEntity extends Entity
{
    use EntityIdTrait;

    protected string $typeTechnicalName;
    protected ?StockMovementProcessTypeEntity $type = null;
    protected array $referencedEntitySnapshot;
    protected ?string $userId;
    protected ?UserEntity $user = null;
    protected ?array $userSnapshot;
    protected ?StockMovementCollection $stockMovements = null;
    protected ?OrderCollection $orders = null;

    public function getTypeTechnicalName(): string
    {
        return $this->typeTechnicalName;
    }

    public function setTypeTechnicalName(string $typeTechnicalName): void
    {
        if ($this->type && $this->type->getTechnicalName() !== $typeTechnicalName) {
            $this->type = null;
        }
        $this->typeTechnicalName = $typeTechnicalName;
    }

    public function getType(): StockMovementProcessTypeEntity
    {
        if (!$this->type) {
            throw new AssociationNotLoadedException('type', $this);
        }

        return $this->type;
    }

    public function setType(StockMovementProcessTypeEntity $type): void
    {
        $this->type = $type;
        $this->typeTechnicalName = $type->getTechnicalName();
    }

    public function getReferencedEntitySnapshot(): array
    {
        return $this->referencedEntitySnapshot;
    }

    public function setReferencedEntitySnapshot(array $referencedEntitySnapshot): void
    {
        $this->referencedEntitySnapshot = $referencedEntitySnapshot;
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

    public function getUserSnapshot(): ?array
    {
        return $this->userSnapshot;
    }

    public function setUserSnapshot(?array $userSnapshot): void
    {
        $this->userSnapshot = $userSnapshot;
    }

    public function getStockMovements(): StockMovementCollection
    {
        if (!$this->stockMovements) {
            throw new AssociationNotLoadedException('stockMovements', $this);
        }

        return $this->stockMovements;
    }

    public function setStockMovements(?StockMovementCollection $stockMovements): void
    {
        $this->stockMovements = $stockMovements;
    }

    public function getOrders(): OrderCollection
    {
        if (!$this->orders) {
            throw new AssociationNotLoadedException('orders', $this);
        }

        return $this->orders;
    }

    public function setOrders(?OrderCollection $orders): void
    {
        $this->orders = $orders;
    }
}
