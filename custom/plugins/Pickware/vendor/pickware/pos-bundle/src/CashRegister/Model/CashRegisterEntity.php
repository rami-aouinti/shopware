<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashRegister\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwarePos\BranchStore\Model\BranchStoreEntity;
use Pickware\PickwarePos\CashRegister\FiscalizationConfiguration;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CashRegisterEntity extends Entity
{
    use EntityIdTrait;

    protected string $branchStoreId;
    protected ?BranchStoreEntity $branchStore = null;
    protected string $name;
    protected ?string $deviceUuid;
    protected ?FiscalizationConfiguration $fiscalizationConfiguration;
    protected ?int $transactionNumberPrefix;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getBranchStoreId(): string
    {
        return $this->branchStoreId;
    }

    public function setBranchStoreId(string $branchStoreId): void
    {
        if ($this->branchStore && $this->branchStore->getId() !== $branchStoreId) {
            $this->branchStore = null;
        }
        $this->branchStoreId = $branchStoreId;
    }

    public function getBranchStore(): BranchStoreEntity
    {
        if (!$this->branchStore && $this->branchStoreId !== null) {
            throw new AssociationNotLoadedException('branchStore', $this);
        }

        return $this->branchStore;
    }

    public function setBranchStore(?BranchStoreEntity $branchStore): void
    {
        if ($branchStore) {
            $this->branchStoreId = $branchStore->getId();
        }
        $this->branchStore = $branchStore;
    }

    public function getDeviceUuid(): ?string
    {
        return $this->deviceUuid;
    }

    public function setDeviceUuid(?string $deviceUuid): void
    {
        $this->deviceUuid = $deviceUuid;
    }

    public function getFiscalizationConfiguration(): ?FiscalizationConfiguration
    {
        return $this->fiscalizationConfiguration;
    }

    public function setFiscalizationConfiguration(?FiscalizationConfiguration $fiscalizationConfiguration): void
    {
        $this->fiscalizationConfiguration = $fiscalizationConfiguration;
    }

    public function getTransactionNumberPrefix(): ?int
    {
        return $this->transactionNumberPrefix;
    }

    public function setTransactionNumberPrefix(?int $transactionNumberPrefix): void
    {
        $this->transactionNumberPrefix = $transactionNumberPrefix;
    }
}
