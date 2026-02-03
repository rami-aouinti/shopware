<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;

class ProductSupplierConfigurationEntity extends Entity
{
    use EntityIdTrait;

    protected string $productId;
    protected ?string $productVersionId;
    protected ?ProductEntity $product = null;
    protected string $supplierId;
    protected ?SupplierEntity $supplier = null;
    protected ?string $supplierProductNumber;
    protected int $minPurchase;
    protected int $purchaseSteps;
    protected bool $supplierIsDefault;
    protected PriceCollection $purchasePrices;
    protected ?int $deliveryTimeDays = null;
    protected ?PurchaseListItemEntity $purchaseListItem = null;

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

    public function getSupplierId(): string
    {
        return $this->supplierId;
    }

    public function setSupplierId(string $supplierId): void
    {
        if ($this->supplier?->getId() !== $supplierId) {
            $this->supplier = null;
        }
        $this->supplierId = $supplierId;
    }

    public function getSupplier(): SupplierEntity
    {
        if (!$this->supplier) {
            throw new AssociationNotLoadedException('supplier', $this);
        }

        return $this->supplier;
    }

    public function setSupplier(?SupplierEntity $supplier): void
    {
        if ($supplier) {
            $this->supplierId = $supplier->getId();
        }
        $this->supplier = $supplier;
    }

    public function getSupplierProductNumber(): ?string
    {
        return $this->supplierProductNumber;
    }

    public function setSupplierProductNumber(?string $supplierProductNumber): void
    {
        $this->supplierProductNumber = $supplierProductNumber;
    }

    public function getMinPurchase(): int
    {
        return $this->minPurchase;
    }

    public function setMinPurchase(int $minPurchase): void
    {
        $this->minPurchase = $minPurchase;
    }

    public function getPurchaseSteps(): int
    {
        return $this->purchaseSteps;
    }

    public function setPurchaseSteps(int $purchaseSteps): void
    {
        $this->purchaseSteps = $purchaseSteps;
    }

    public function getPurchasePrices(): PriceCollection
    {
        return $this->purchasePrices;
    }

    public function setPurchasePrices(PriceCollection $purchasePrices): void
    {
        $this->purchasePrices = $purchasePrices;
    }

    public function getSupplierIsDefault(): bool
    {
        return $this->supplierIsDefault;
    }

    public function setSupplierIsDefault(bool $supplierIsDefault): void
    {
        $this->supplierIsDefault = $supplierIsDefault;
    }

    public function getDeliveryTimeDays(): ?int
    {
        return $this->deliveryTimeDays;
    }

    public function setDeliveryTimeDays(?int $deliveryTimeDays): void
    {
        $this->deliveryTimeDays = $deliveryTimeDays;
    }

    public function getPurchaseListItem(): ?PurchaseListItemEntity
    {
        return $this->purchaseListItem;
    }

    public function setPurchaseListItem(?PurchaseListItemEntity $purchaseListItem): void
    {
        $this->purchaseListItem = $purchaseListItem;
    }
}
