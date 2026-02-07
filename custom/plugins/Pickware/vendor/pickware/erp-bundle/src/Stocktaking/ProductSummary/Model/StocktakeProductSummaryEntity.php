<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\ProductSummary\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class StocktakeProductSummaryEntity extends Entity
{
    use EntityIdTrait;

    protected int $countedStock;
    protected int $absoluteStockDifference;
    protected int $percentageStockDifference;
    protected string $stocktakeId;
    protected ?StocktakeEntity $stocktake = null;
    protected string $productId;
    protected string $productVersionId;
    protected ?ProductEntity $product = null;

    public function getCountedStock(): int
    {
        return $this->countedStock;
    }

    public function setCountedStock(int $countedStock): void
    {
        $this->countedStock = $countedStock;
    }

    public function getAbsoluteStockDifference(): int
    {
        return $this->absoluteStockDifference;
    }

    public function setAbsoluteStockDifference(int $absoluteStockDifference): void
    {
        $this->absoluteStockDifference = $absoluteStockDifference;
    }

    public function getPercentageStockDifference(): int
    {
        return $this->percentageStockDifference;
    }

    public function setPercentageStockDifference(int $percentageStockDifference): void
    {
        $this->percentageStockDifference = $percentageStockDifference;
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

    public function getProductVersionId(): string
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
}
