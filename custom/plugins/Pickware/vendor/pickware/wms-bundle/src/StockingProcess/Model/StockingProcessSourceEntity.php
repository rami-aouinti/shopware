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
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptEntity;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeEntity;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerEntity;
use Pickware\PickwareErpStarter\Stock\Model\StockLocationReferenceTrait;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class StockingProcessSourceEntity extends Entity
{
    use EntityIdTrait;
    use StockLocationReferenceTrait;

    protected string $stockingProcessId;
    protected ?StockingProcessEntity $stockingProcess = null;
    protected string $locationTypeTechnicalName;
    protected ?LocationTypeEntity $locationType = null;
    protected ?string $goodsReceiptId = null;
    protected ?GoodsReceiptEntity $goodsReceipt = null;
    protected ?string $stockContainerId = null;
    protected ?StockContainerEntity $stockContainer = null;
    protected ?string $warehouseId = null;
    protected ?WarehouseEntity $warehouse = null;

    public function getStockingProcessId(): string
    {
        return $this->stockingProcessId;
    }

    public function setStockingProcessId(string $stockingProcessId): void
    {
        if ($this->stockingProcess?->getId() !== $stockingProcessId) {
            $this->stockingProcess = null;
        }
        $this->stockingProcessId = $stockingProcessId;
    }

    public function getStockingProcess(): StockingProcessEntity
    {
        if (!$this->stockingProcess) {
            throw new AssociationNotLoadedException('stockingProcess', $this);
        }

        return $this->stockingProcess;
    }

    public function setStockingProcess(StockingProcessEntity $stockingProcess): void
    {
        $this->stockingProcessId = $stockingProcess->getId();
        $this->stockingProcess = $stockingProcess;
    }

    public function getLocationTypeTechnicalName(): string
    {
        return $this->locationTypeTechnicalName;
    }

    public function setLocationTypeTechnicalName(string $locationTypeTechnicalName): void
    {
        if ($this->locationType?->getTechnicalName() !== $locationTypeTechnicalName) {
            $this->locationType = null;
        }
        $this->locationTypeTechnicalName = $locationTypeTechnicalName;
    }

    public function getLocationType(): LocationTypeEntity
    {
        if (!$this->locationType) {
            throw new AssociationNotLoadedException('locationType', $this);
        }

        return $this->locationType;
    }

    public function setLocationType(LocationTypeEntity $locationType): void
    {
        $this->locationTypeTechnicalName = $locationType->getTechnicalName();
        $this->locationType = $locationType;
    }

    public function getGoodsReceiptId(): ?string
    {
        return $this->goodsReceiptId;
    }

    public function setGoodsReceiptId(?string $goodsReceiptId): void
    {
        if ($this->goodsReceipt?->getId() !== $goodsReceiptId) {
            $this->goodsReceipt = null;
        }
        $this->goodsReceiptId = $goodsReceiptId;
    }

    public function getGoodsReceipt(): ?GoodsReceiptEntity
    {
        if (!$this->goodsReceipt && $this->goodsReceiptId) {
            throw new AssociationNotLoadedException('goodsReceipt', $this);
        }

        return $this->goodsReceipt;
    }

    public function setGoodsReceipt(?GoodsReceiptEntity $goodsReceipt): void
    {
        $this->goodsReceiptId = $goodsReceipt?->getId();
        $this->goodsReceipt = $goodsReceipt;
    }

    public function getStockContainerId(): ?string
    {
        return $this->stockContainerId;
    }

    public function setStockContainerId(?string $stockContainerId): void
    {
        if ($this->stockContainer?->getId() !== $stockContainerId) {
            $this->stockContainer = null;
        }
        $this->stockContainerId = $stockContainerId;
    }

    public function getStockContainer(): ?StockContainerEntity
    {
        if (!$this->stockContainer && $this->stockContainerId) {
            throw new AssociationNotLoadedException('stockContainer', $this);
        }

        return $this->stockContainer;
    }

    public function setStockContainer(?StockContainerEntity $stockContainer): void
    {
        $this->stockContainerId = $stockContainer?->getId();
        $this->stockContainer = $stockContainer;
    }

    public function getWarehouseId(): ?string
    {
        return $this->warehouseId;
    }

    public function setWarehouseId(?string $warehouseId): void
    {
        if ($this->warehouse?->getId() !== $warehouseId) {
            $this->warehouse = null;
        }
        $this->warehouseId = $warehouseId;
    }

    public function getWarehouse(): ?WarehouseEntity
    {
        if (!$this->warehouse && $this->warehouseId) {
            throw new AssociationNotLoadedException('warehouse', $this);
        }

        return $this->warehouse;
    }

    public function setWarehouse(?WarehouseEntity $warehouse): void
    {
        $this->warehouseId = $warehouse?->getId();
        $this->warehouse = $warehouse;
    }
}
