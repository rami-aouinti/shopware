<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\Batch\Model\BatchEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\StockValuation\Model\PurchaseEntity;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\ProductSnapshotGenerator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * @phpstan-import-type ProductSnapshot from ProductSnapshotGenerator
 */
class GoodsReceiptLineItemEntity extends Entity
{
    use EntityIdTrait;

    protected string $goodsReceiptId;
    protected ?GoodsReceiptEntity $goodsReceipt = null;
    protected int $quantity;
    protected ?string $productId = null;
    protected ?string $productVersionId = null;
    protected ?ProductEntity $product = null;
    protected ?string $batchId = null;
    protected ?BatchEntity $batch = null;

    /**
     * @var ProductSnapshot
     */
    protected array $productSnapshot;

    protected ?string $destinationBinLocationId = null;
    protected GoodsReceiptLineItemDestinationAssignmentSource $destinationAssignmentSource;
    protected ?BinLocationEntity $destinationBinLocation = null;
    protected ?CalculatedPrice $price;
    protected ?QuantityPriceDefinition $priceDefinition;
    protected ?float $unitPrice;
    protected ?float $totalPrice;
    protected ?string $supplierOrderId = null;
    protected ?SupplierOrderEntity $supplierOrder = null;
    protected ?string $returnOrderId = null;
    protected ?ReturnOrderEntity $returnOrder = null;
    protected ?StateMachineStateEntity $state = null;
    protected ?PurchaseEntity $purchase = null;

    public function getGoodsReceiptId(): string
    {
        return $this->goodsReceiptId;
    }

    public function setGoodsReceiptId(string $goodsReceiptId): void
    {
        if ($this->goodsReceipt && $this->goodsReceipt->getId() !== $goodsReceiptId) {
            $this->goodsReceipt = null;
        }
        $this->goodsReceiptId = $goodsReceiptId;
    }

    public function getGoodsReceipt(): GoodsReceiptEntity
    {
        if (!$this->goodsReceipt) {
            throw new AssociationNotLoadedException('goodsReceipt', $this);
        }

        return $this->goodsReceipt;
    }

    public function setGoodsReceipt(?GoodsReceiptEntity $goodsReceipt): void
    {
        if ($goodsReceipt) {
            $this->goodsReceiptId = $goodsReceipt->getId();
        }
        $this->goodsReceipt = $goodsReceipt;
    }

    /**
     * @return non-negative-int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getProductId(): ?string
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

    public function getProduct(): ?ProductEntity
    {
        if ($this->productId && !$this->product) {
            throw new AssociationNotLoadedException('product', $this);
        }

        return $this->product;
    }

    public function setProduct(ProductEntity $product): void
    {
        $this->productId = $product->getId();
        $this->product = $product;
    }

    public function getProductVersionId(): ?string
    {
        return $this->productVersionId;
    }

    public function setProductVersionId(?string $productVersionId): void
    {
        $this->productVersionId = $productVersionId;
    }

    public function getBatchId(): ?string
    {
        return $this->batchId;
    }

    public function setBatchId(?string $batchId): void
    {
        if ($this->batch && $this->batch->getId() !== $batchId) {
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
        if ($batch) {
            $this->batchId = $batch->getId();
        }
        $this->batch = $batch;
    }

    /**
     * @return ProductSnapshot
     */
    public function getProductSnapshot(): array
    {
        return $this->productSnapshot;
    }

    /**
     * @param ProductSnapshot $productSnapshot
     */
    public function setProductSnapshot(array $productSnapshot): void
    {
        $this->productSnapshot = $productSnapshot;
    }

    public function getDestinationAssignmentSource(): GoodsReceiptLineItemDestinationAssignmentSource
    {
        return $this->destinationAssignmentSource;
    }

    public function setDestinationAssignmentSource(
        GoodsReceiptLineItemDestinationAssignmentSource $destinationAssignmentSource,
    ): void {
        $this->destinationAssignmentSource = $destinationAssignmentSource;
    }

    public function getDestinationBinLocationId(): ?string
    {
        return $this->destinationBinLocationId;
    }

    public function setDestinationBinLocationId(?string $destinationBinLocationId): void
    {
        if (
            $this->destinationBinLocation
            && $this->destinationBinLocation->getId() !== $destinationBinLocationId
        ) {
            $this->destinationBinLocation = null;
        }
        $this->destinationBinLocationId = $destinationBinLocationId;
    }

    public function getDestinationBinLocation(): ?BinLocationEntity
    {
        if ($this->destinationBinLocationId && !$this->destinationBinLocation) {
            throw new AssociationNotLoadedException('destinationBinLocation', $this);
        }

        return $this->destinationBinLocation;
    }

    public function setDestinationBinLocation(?BinLocationEntity $destinationBinLocation): void
    {
        if ($destinationBinLocation) {
            $this->destinationBinLocationId = $destinationBinLocation->getId();
        }
        $this->destinationBinLocation = $destinationBinLocation;
    }

    public function getPrice(): ?CalculatedPrice
    {
        return $this->price;
    }

    public function setPrice(?CalculatedPrice $price): void
    {
        $this->price = $price;
    }

    public function getPriceDefinition(): ?QuantityPriceDefinition
    {
        return $this->priceDefinition;
    }

    public function setPriceDefinition(?QuantityPriceDefinition $priceDefinition): void
    {
        $this->priceDefinition = $priceDefinition;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?float $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function getTotalPrice(): ?float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(?float $totalPrice): void
    {
        $this->totalPrice = $totalPrice;
    }

    public function getSupplierOrderId(): ?string
    {
        return $this->supplierOrderId;
    }

    public function setSupplierOrderId(?string $supplierOrderId): void
    {
        if ($this->supplierOrder && $this->supplierOrder->getId() !== $supplierOrderId) {
            $this->supplierOrder = null;
        }
        $this->supplierOrderId = $supplierOrderId;
    }

    public function getSupplierOrder(): ?SupplierOrderEntity
    {
        if ($this->supplierOrderId && !$this->supplierOrder) {
            throw new AssociationNotLoadedException('supplierOrder', $this);
        }

        return $this->supplierOrder;
    }

    public function setSupplierOrder(?SupplierOrderEntity $supplierOrder): void
    {
        if ($supplierOrder) {
            $this->supplierOrderId = $supplierOrder->getId();
        }
        $this->supplierOrder = $supplierOrder;
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

    public function getReturnOrder(): ?ReturnOrderEntity
    {
        if ($this->returnOrderId && !$this->returnOrder) {
            throw new AssociationNotLoadedException('returnOrder', $this);
        }

        return $this->returnOrder;
    }

    public function setReturnOrder(?ReturnOrderEntity $returnOrder): void
    {
        if ($returnOrder) {
            $this->returnOrderId = $returnOrder->getId();
        }
        $this->returnOrder = $returnOrder;
    }

    public function getState(): ?StateMachineStateEntity
    {
        return $this->state;
    }

    public function setState(?StateMachineStateEntity $state): void
    {
        $this->state = $state;
    }

    public function getPurchase(): ?PurchaseEntity
    {
        return $this->purchase;
    }

    public function setPurchase(?PurchaseEntity $purchase): void
    {
        $this->purchase = $purchase;
    }
}
