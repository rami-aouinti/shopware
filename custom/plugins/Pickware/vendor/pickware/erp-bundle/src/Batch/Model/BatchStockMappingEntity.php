<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class BatchStockMappingEntity extends Entity
{
    use EntityIdTrait;

    protected string $stockId;
    protected ?StockEntity $stock = null;
    protected string $batchId;
    protected ?BatchEntity $batch = null;
    protected int $quantity;
    protected string $productId;
    protected ?string $productVersionId;
    protected ?ProductEntity $product = null;

    public function getStockId(): string
    {
        return $this->stockId;
    }

    public function setStockId(string $stockId): void
    {
        if ($this->stock && $this->stock->getId() !== $stockId) {
            $this->stock = null;
        }
        $this->stockId = $stockId;
    }

    public function getStock(): StockEntity
    {
        if (!$this->stock) {
            throw new AssociationNotLoadedException('stock', $this);
        }

        return $this->stock;
    }

    public function setStock(StockEntity $stock): void
    {
        $this->stock = $stock;
        $this->stockId = $stock->getId();
    }

    public function getBatchId(): string
    {
        return $this->batchId;
    }

    public function setBatchId(string $batchId): void
    {
        if ($this->batch && $this->batch->getId() !== $batchId) {
            $this->batch = null;
        }
        $this->batchId = $batchId;
    }

    public function getBatch(): BatchEntity
    {
        if (!$this->batch) {
            throw new AssociationNotLoadedException('batch', $this);
        }

        return $this->batch;
    }

    public function setBatch(BatchEntity $batch): void
    {
        $this->batch = $batch;
        $this->batchId = $batch->getId();
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

    public function setProduct(ProductEntity $product): void
    {
        $this->product = $product;
        $this->productId = $product->getId();
        $this->productVersionId = $product->getVersionId();
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }
}
