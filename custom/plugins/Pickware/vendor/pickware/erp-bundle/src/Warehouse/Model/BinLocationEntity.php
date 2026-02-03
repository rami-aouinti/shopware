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
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class BinLocationEntity extends Entity
{
    use EntityIdTrait;

    protected string $code;
    protected string $warehouseId;
    protected ?WarehouseEntity $warehouse = null;
    protected ?int $position;
    protected ?StockMovementCollection $sourceStockMovements = null;
    protected ?StockMovementCollection $destinationStockMovements = null;
    protected ?StockCollection $stocks = null;
    protected ?ProductWarehouseConfigurationCollection $productWarehouseConfigurations = null;
    protected ?GoodsReceiptLineItemCollection $destinationGoodsReceiptLineItems = null;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function getSourceStockMovements(): StockMovementCollection
    {
        if (!$this->sourceStockMovements) {
            throw new AssociationNotLoadedException('sourceStockMovements', $this);
        }

        return $this->sourceStockMovements;
    }

    public function setSourceStockMovements(?StockMovementCollection $sourceStockMovements): void
    {
        $this->sourceStockMovements = $sourceStockMovements;
    }

    public function getDestinationStockMovements(): StockMovementCollection
    {
        if (!$this->destinationStockMovements) {
            throw new AssociationNotLoadedException('destinationStockMovements', $this);
        }

        return $this->destinationStockMovements;
    }

    public function setDestinationStockMovements(?StockMovementCollection $destinationStockMovements): void
    {
        $this->destinationStockMovements = $destinationStockMovements;
    }

    public function getStocks(): StockCollection
    {
        if (!$this->stocks) {
            throw new AssociationNotLoadedException('stocks', $this);
        }

        return $this->stocks;
    }

    public function getStockForProduct(string $productId): ?StockEntity
    {
        if (!$this->stocks) {
            throw new AssociationNotLoadedException('stocks', $this);
        }

        return $this->stocks->filter(fn(StockEntity $stock) => $stock->getProductId() === $productId)->first();
    }

    public function setStocks(?StockCollection $stocks): void
    {
        $this->stocks = $stocks;
    }

    public function getProductWarehouseConfigurations(): ProductWarehouseConfigurationCollection
    {
        if (!$this->productWarehouseConfigurations) {
            throw new AssociationNotLoadedException('productWarehouseConfigurations', $this);
        }

        return $this->productWarehouseConfigurations;
    }

    public function setProductWarehouseConfigurations(
        ?ProductWarehouseConfigurationCollection $productWarehouseConfigurations,
    ): void {
        $this->productWarehouseConfigurations = $productWarehouseConfigurations;
    }

    public function getDestinationGoodsReceiptLineItems(): ?GoodsReceiptLineItemCollection
    {
        if (!$this->destinationGoodsReceiptLineItems) {
            throw new AssociationNotLoadedException('destinationGoodsReceiptLineItems', $this);
        }

        return $this->destinationGoodsReceiptLineItems;
    }

    public function setDestinationGoodsReceiptLineItems(?GoodsReceiptLineItemCollection $destinationGoodsReceiptLineItems): void
    {
        $this->destinationGoodsReceiptLineItems = $destinationGoodsReceiptLineItems;
    }
}
