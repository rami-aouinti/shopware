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
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ProductStockLocationMappingEntity extends Entity
{
    use EntityIdTrait;

    protected string $productId;
    protected ?string $productVersionId;
    protected ?ProductEntity $product = null;
    protected ?string $warehouseId = null;
    protected ?WarehouseEntity $warehouse = null;
    protected ?string $binLocationId = null;
    protected ?BinLocationEntity $binLocation = null;
    protected ?string $stockId = null;
    protected ?StockEntity $stock = null;
    protected ConfigurableStockLocation $stockLocationType;
    protected ?ProductStockLocationConfigurationEntity $productStockLocationConfiguration = null;

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

    public function setProduct(ProductEntity $product): void
    {
        $this->productId = $product->getId();
        $this->productVersionId = $product->getVersionId();
        $this->product = $product;
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
        if ($this->warehouseId && !$this->warehouse) {
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
        if ($this->binLocationId && !$this->binLocation) {
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

    public function getStockId(): ?string
    {
        return $this->stockId;
    }

    public function setStockId(?string $stockId): void
    {
        if ($this->stock && $this->stock->getId() !== $stockId) {
            $this->stock = null;
        }
        $this->stockId = $stockId;
    }

    public function getStock(): ?StockEntity
    {
        return $this->stock;
    }

    public function setStock(?StockEntity $stock): void
    {
        if ($stock) {
            $this->stockId = $stock->getId();
        }
        $this->stock = $stock;
    }

    public function getStockLocationType(): ConfigurableStockLocation
    {
        return $this->stockLocationType;
    }

    public function setStockLocationType(ConfigurableStockLocation $stockLocationType): void
    {
        $this->stockLocationType = $stockLocationType;
    }

    public function getProductStockLocationConfiguration(): ?ProductStockLocationConfigurationEntity
    {
        return $this->productStockLocationConfiguration;
    }

    public function setProductStockLocationConfiguration(?ProductStockLocationConfigurationEntity $productStockLocationConfiguration): void
    {
        $this->productStockLocationConfiguration = $productStockLocationConfiguration;
    }
}
