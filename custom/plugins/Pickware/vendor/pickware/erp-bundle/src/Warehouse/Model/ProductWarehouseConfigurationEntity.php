<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Warehouse\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\Stock\Model\WarehouseStockEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ProductWarehouseConfigurationEntity extends Entity
{
    use EntityIdTrait;

    protected string $warehouseId;
    protected ?WarehouseEntity $warehouse = null;
    protected string $productId;
    protected ?string $productVersionId;
    protected ?ProductEntity $product = null;
    protected ?string $defaultBinLocationId;
    protected ?BinLocationEntity $defaultBinLocation = null;
    protected int $reorderPoint;
    protected ?int $targetMaximumQuantity = null;
    protected ?int $stockBelowReorderPoint = null;
    protected ?string $warehouseStockId = null;
    protected ?WarehouseStockEntity $warehouseStock = null;

    public function getWarehouseId(): string
    {
        return $this->warehouseId;
    }

    public function setWarehouseId(string $warehouseId): void
    {
        if ($this->warehouse && $this->warehouse->getId() !== $warehouseId) {
            $this->warehouse = null;
        }

        $this->warehouseId = $warehouseId;
    }

    public function getWarehouse(): WarehouseEntity
    {
        if (!$this->warehouse) {
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

    public function setProductVersionId(string $productVersionId): void
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

    public function getDefaultBinLocationId(): ?string
    {
        return $this->defaultBinLocationId;
    }

    public function setDefaultBinLocationId(?string $defaultBinLocationId): void
    {
        if ($this->defaultBinLocation && $this->defaultBinLocation->getId() !== $defaultBinLocationId) {
            $this->defaultBinLocation = null;
        }

        $this->defaultBinLocationId = $defaultBinLocationId;
    }

    public function getDefaultBinLocation(): ?BinLocationEntity
    {
        if (!$this->defaultBinLocation && $this->defaultBinLocationId) {
            throw new AssociationNotLoadedException('defaultBinLocation', $this);
        }

        return $this->defaultBinLocation;
    }

    public function setDefaultBinLocation(?BinLocationEntity $defaultBinLocation): void
    {
        if ($defaultBinLocation) {
            $this->defaultBinLocationId = $defaultBinLocation->getId();
        }
        $this->defaultBinLocation = $defaultBinLocation;
    }

    public function getReorderPoint(): int
    {
        return $this->reorderPoint;
    }

    public function setReorderPoint(int $reorderPoint): void
    {
        $this->reorderPoint = $reorderPoint;
    }

    public function getTargetMaximumQuantity(): ?int
    {
        return $this->targetMaximumQuantity;
    }

    public function setTargetMaximumQuantity(?int $targetMaximumQuantity): void
    {
        $this->targetMaximumQuantity = $targetMaximumQuantity;
    }

    public function getStockBelowReorderPoint(): ?int
    {
        return $this->stockBelowReorderPoint;
    }

    public function setStockBelowReorderPoint(?int $stockBelowReorderPoint): void
    {
        $this->stockBelowReorderPoint = $stockBelowReorderPoint;
    }

    public function getWarehouseStockId(): ?string
    {
        return $this->warehouseStockId;
    }

    public function setWarehouseStockId(?string $warehouseStockId): void
    {
        if ($this->warehouseStock && $this->warehouseStock->getId() !== $warehouseStockId) {
            $this->warehouseStock = null;
        }
        $this->warehouseStockId = $warehouseStockId;
    }

    public function getWarehouseStock(): ?WarehouseStockEntity
    {
        return $this->warehouseStock;
    }

    public function setWarehouseStock(?WarehouseStockEntity $warehouseStock): void
    {
        $this->warehouseStockId = $warehouseStock?->getId();
        $this->warehouseStock = $warehouseStock;
    }
}
