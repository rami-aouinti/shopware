<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\Batch\Model\BatchStockMappingCollection;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptEntity;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class StockEntity extends Entity
{
    use EntityIdTrait;
    use StockLocationReferenceTrait;

    protected int $quantity;
    protected string $productId;
    protected ?string $productVersionId;
    protected ?ProductEntity $product = null;
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
    protected ?string $returnOrderVersionId;
    protected ?ReturnOrderEntity $returnOrder = null;
    protected ?string $stockContainerId = null;
    protected ?StockContainerEntity $stockContainer = null;
    protected ?string $goodsReceiptId = null;
    protected ?GoodsReceiptEntity $goodsReceipt = null;
    protected ?string $specialStockLocationTechnicalName = null;
    protected ?SpecialStockLocationEntity $specialStockLocation = null;
    protected ?BatchStockMappingCollection $batchMappings = null;
    protected ?ProductStockLocationMappingEntity $binLocationMapping = null;

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        if ($this->product && $this->product->getId() !== $productId) {
            $this->product = null;
        }
        $this->productId = $productId;
    }

    public function getProductVersionId(): ?string
    {
        return $this->productVersionId;
    }

    public function setProductVersionId(?string $productVersionId): void
    {
        if ($this->product && $this->product->getVersionId() !== $productVersionId) {
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

    public function setProduct(?ProductEntity $product): void
    {
        if ($product) {
            $this->productId = $product->getId();
            $this->productVersionId = $product->getVersionId();
        }
        $this->product = $product;
    }

    public function getLocationTypeTechnicalName(): string
    {
        return $this->locationTypeTechnicalName;
    }

    public function setLocationTypeTechnicalName(string $locationTypeTechnicalName): void
    {
        if ($this->locationType && $this->locationType->getTechnicalName() !== $locationTypeTechnicalName) {
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
        if ($this->warehouse && $this->warehouse->getId() !== $warehouseId) {
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
        if ($warehouse) {
            $this->warehouseId = $warehouse->getId();
        }
        $this->warehouse = $warehouse;
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

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        if ($this->order && $this->order->getId() !== $orderId) {
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
        if ($this->order && $this->order->getVersionId() !== $orderVersionId) {
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
        if ($order) {
            $this->orderId = $order->getId();
        } else {
            $this->orderId = null;
        }
        $this->order = $order;
    }

    public function getReturnOrderId(): ?string
    {
        return $this->returnOrderId;
    }

    public function setReturnOrderId(?string $returnOrderId): void
    {
        if ($this->returnOrder && $this->returnOrder->getId() !== $returnOrderId) {
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
        if ($this->returnOrder && $this->returnOrder->getVersionId() !== $returnOrderVersionId) {
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
        if ($returnOrder) {
            $this->returnOrderId = $returnOrder->getId();
            $this->returnOrderVersionId = $returnOrder->getVersionId();
        }
        $this->returnOrder = $returnOrder;
    }

    public function getStockContainerId(): ?string
    {
        return $this->stockContainerId;
    }

    public function setStockContainerId(?string $stockContainerId): void
    {
        if ($stockContainerId && $this->stockContainer && $this->stockContainer->getId() !== $stockContainerId) {
            $this->stockContainer = null;
        }
        $this->stockContainerId = $stockContainerId;
    }

    public function getStockContainer(): ?StockContainerEntity
    {
        if ($this->stockContainerId && !$this->stockContainer) {
            throw new AssociationNotLoadedException('stockContainer', $this);
        }

        return $this->stockContainer;
    }

    public function setStockContainer(?StockContainerEntity $stockContainer): void
    {
        if ($stockContainer) {
            $this->stockContainerId = $stockContainer->getId();
        } else {
            $this->stockContainerId = null;
        }
        $this->stockContainer = $stockContainer;
    }

    public function getGoodsReceiptId(): ?string
    {
        return $this->goodsReceiptId;
    }

    public function setGoodsReceiptId(?string $goodsReceiptId): void
    {
        if ($goodsReceiptId && $this->goodsReceipt && $this->goodsReceipt->getId() !== $goodsReceiptId) {
            $this->goodsReceipt = null;
        }
        $this->goodsReceiptId = $goodsReceiptId;
    }

    public function getGoodsReceipt(): ?GoodsReceiptEntity
    {
        if ($this->goodsReceiptId && !$this->goodsReceipt) {
            throw new AssociationNotLoadedException('goodsReceipt', $this);
        }

        return $this->goodsReceipt;
    }

    public function setGoodsReceipt(?GoodsReceiptEntity $goodsReceipt): void
    {
        if ($goodsReceipt) {
            $this->goodsReceiptId = $goodsReceipt->getId();
        } else {
            $this->goodsReceiptId = null;
        }
        $this->goodsReceipt = $goodsReceipt;
    }

    public function getSpecialStockLocationTechnicalName(): ?string
    {
        return $this->specialStockLocationTechnicalName;
    }

    public function setSpecialStockLocationTechnicalName(?string $specialStockLocationTechnicalName): void
    {
        if (
            $this->specialStockLocation
            && $this->specialStockLocation->getTechnicalName() !== $specialStockLocationTechnicalName
        ) {
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
        if ($specialStockLocation) {
            $this->specialStockLocationTechnicalName = $specialStockLocation->getTechnicalName();
        }
        $this->specialStockLocation = $specialStockLocation;
    }

    public function getProductQuantityLocation(): ProductQuantityLocation
    {
        $batches = null;
        if ($this->batchMappings?->count() > 0) {
            $batches = $this->batchMappings->asBatchQuantityMap();
        }

        return new ProductQuantityLocation(
            locationReference: $this->getStockLocationReference(),
            productId: $this->productId,
            quantity: $this->quantity,
            batches: $batches,
        );
    }

    public function getProductQuantity(): ProductQuantity
    {
        $batches = null;
        if ($this->batchMappings?->count() > 0) {
            $batches = $this->batchMappings->asBatchQuantityMap();
        }

        return new ProductQuantity(
            productId: $this->productId,
            quantity: $this->quantity,
            batches: $batches,
        );
    }

    public function getBatchMappings(): BatchStockMappingCollection
    {
        if (!$this->batchMappings) {
            throw new AssociationNotLoadedException('batchMappings', $this);
        }

        return $this->batchMappings;
    }

    public function setBatchMappings(?BatchStockMappingCollection $batchMappings): void
    {
        $this->batchMappings = $batchMappings;
    }

    public function getBinLocationMapping(): ProductStockLocationMappingEntity
    {
        if (!$this->binLocationMapping) {
            throw new AssociationNotLoadedException('binLocationMapping', $this);
        }

        return $this->binLocationMapping;
    }

    public function setBinLocationMapping(?ProductStockLocationMappingEntity $binLocationMapping): void
    {
        $this->binLocationMapping = $binLocationMapping;
    }
}
