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
use Pickware\PickwareErpStarter\Batch\Model\BatchEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeEntity;
use Pickware\PickwareErpStarter\Stock\Model\SpecialStockLocationEntity;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerEntity;
use Pickware\PickwareErpStarter\Stock\Model\StockLocationReferenceTrait;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class StockingProcessLineItemEntity extends Entity
{
    use EntityIdTrait;
    use StockLocationReferenceTrait;

    protected string $stockingProcessId;
    protected ?StockingProcessEntity $stockingProcess = null;
    protected string $productId;
    protected string $productVersionId;
    protected ?ProductEntity $product = null;
    protected ?string $batchId;
    protected ?BatchEntity $batch = null;
    protected int $quantity;
    protected int $position;
    protected string $locationTypeTechnicalName;
    protected ?LocationTypeEntity $locationType = null;
    protected ?string $warehouseId = null;
    protected ?WarehouseEntity $warehouse = null;
    protected ?string $binLocationId = null;
    protected ?BinLocationEntity $binLocation = null;
    protected ?string $orderId = null;
    protected ?string $orderVersionId = null;
    protected ?OrderEntity $order = null;
    protected ?string $returnOrderId = null;
    protected ?string $returnOrderVersionId = null;
    protected ?ReturnOrderEntity $returnOrder = null;
    protected ?string $stockContainerId = null;
    protected ?StockContainerEntity $stockContainer = null;
    protected ?string $specialStockLocationTechnicalName = null;
    protected ?SpecialStockLocationEntity $specialStockLocation = null;

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

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        if ($this->product?->getId() !== $productId) {
            $this->product = null;
        }
        $this->productId = $productId;
    }

    public function getProductVersionId(): string
    {
        return $this->productVersionId;
    }

    public function setProductVersionId(string $productVersionId): void
    {
        if ($this->product?->getVersionId() !== $productVersionId) {
            $this->product = null;
        }
        $this->productVersionId = $productVersionId;
    }

    public function getProduct(): ProductEntity
    {
        if (!$this->product) {
            throw new AssociationNotLoadedException('product', $this);
        }

        return $this->product;
    }

    public function setProduct(ProductEntity $product): void
    {
        $this->productId = $product->getId();
        $this->productVersionId = $product->getVersionId();
        $this->product = $product;
    }

    public function getBatchId(): ?string
    {
        return $this->batchId;
    }

    public function setBatchId(?string $batchId): void
    {
        if ($this->batch?->getId() !== $batchId) {
            $this->batch = null;
        }
        $this->batchId = $batchId;
    }

    public function getBatch(): ?BatchEntity
    {
        if ($this->batchId && !$this->batch) {
            throw new AssociationNotLoadedException('batch', $this);
        }

        return $this->batch;
    }

    public function setBatch(?BatchEntity $batch): void
    {
        $this->batchId = $batch?->getId();
        $this->batch = $batch;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
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

    public function getBinLocationId(): ?string
    {
        return $this->binLocationId;
    }

    public function setBinLocationId(?string $binLocationId): void
    {
        if ($this->binLocation?->getId() !== $binLocationId) {
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
        $this->binLocationId = $binLocation?->getId();
        $this->binLocation = $binLocation;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        if ($this->order?->getId() !== $orderId) {
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
        if ($this->order?->getVersionId() !== $orderVersionId) {
            $this->order = null;
        }
        $this->orderVersionId = $orderVersionId;
    }

    public function getOrder(): ?OrderEntity
    {
        if (!$this->order && $this->orderId) {
            throw new AssociationNotLoadedException('order', $this);
        }

        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->orderId = $order?->getId();
        $this->orderVersionId = $order?->getVersionId();
        $this->order = $order;
    }

    public function getReturnOrderId(): ?string
    {
        return $this->returnOrderId;
    }

    public function setReturnOrderId(?string $returnOrderId): void
    {
        if ($this->returnOrder?->getId() !== $returnOrderId) {
            $this->returnOrder = null;
        }
        $this->returnOrderId = $returnOrderId;
    }

    public function getReturnOrderVersionId(): ?string
    {
        return $this->returnOrderVersionId;
    }

    public function setReturnOrderVersionId(?string $returnOrderVersionId): void
    {
        if ($this->returnOrder?->getVersionId() !== $returnOrderVersionId) {
            $this->returnOrder = null;
        }
        $this->returnOrderVersionId = $returnOrderVersionId;
    }

    public function getReturnOrder(): ?ReturnOrderEntity
    {
        if (!$this->returnOrder && $this->returnOrderId) {
            throw new AssociationNotLoadedException('returnOrder', $this);
        }

        return $this->returnOrder;
    }

    public function setReturnOrder(?ReturnOrderEntity $returnOrder): void
    {
        $this->returnOrderId = $returnOrder?->getId();
        $this->returnOrderVersionId = $returnOrder?->getVersionId();
        $this->returnOrder = $returnOrder;
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

    public function getSpecialStockLocationTechnicalName(): ?string
    {
        return $this->specialStockLocationTechnicalName;
    }

    public function setSpecialStockLocationTechnicalName(?string $specialStockLocationTechnicalName): void
    {
        if ($this->specialStockLocation?->getTechnicalName() !== $specialStockLocationTechnicalName) {
            $this->specialStockLocation = null;
        }
        $this->specialStockLocationTechnicalName = $specialStockLocationTechnicalName;
    }

    public function getSpecialStockLocation(): ?SpecialStockLocationEntity
    {
        if (!$this->specialStockLocation && $this->specialStockLocationTechnicalName) {
            throw new AssociationNotLoadedException('specialStockLocation', $this);
        }

        return $this->specialStockLocation;
    }

    public function setSpecialStockLocation(?SpecialStockLocationEntity $specialStockLocation): void
    {
        $this->specialStockLocationTechnicalName = $specialStockLocation?->getTechnicalName();
        $this->specialStockLocation = $specialStockLocation;
    }
}
