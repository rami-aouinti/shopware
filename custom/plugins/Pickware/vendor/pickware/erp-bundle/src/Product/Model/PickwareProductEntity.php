<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Product\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareErpStarter\Supplier\Model\SupplierEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PickwareProductEntity extends Entity
{
    use EntityIdTrait;

    protected string $productId;
    protected ?string $productVersionId;
    protected ?ProductEntity $product = null;
    protected int $reorderPoint;
    protected ?int $targetMaximumQuantity;
    protected ?int $stockBelowReorderPoint;
    protected int $incomingStock;
    protected int $reservedStock;
    protected int $internalReservedStock;
    protected int $externalReservedStock;
    protected int $physicalStock;
    protected int $stockNotAvailableForSale;
    protected bool $shipAutomatically;
    protected bool $isStockManagementDisabled;
    protected bool $isBatchManaged;
    protected bool $isExcludedFromReorderNotificationMail;
    protected ProductTrackingProfile $trackingProfile;
    protected ?string $defaultSupplierId;
    protected ?SupplierEntity $defaultSupplier = null;

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

    public function getIncomingStock(): int
    {
        return $this->incomingStock;
    }

    public function setIncomingStock(int $incomingStock): void
    {
        $this->incomingStock = $incomingStock;
    }

    public function getIsStockManagementDisabled(): bool
    {
        return $this->isStockManagementDisabled;
    }

    public function setIsStockManagementDisabled(bool $isStockManagementDisabled): void
    {
        $this->isStockManagementDisabled = $isStockManagementDisabled;
    }

    public function getIsBatchManaged(): bool
    {
        return $this->isBatchManaged;
    }

    public function setIsBatchManaged(bool $isBatchManaged): void
    {
        $this->isBatchManaged = $isBatchManaged;
    }

    public function getIsExcludedFromReorderNotificationMail(): bool
    {
        return $this->isExcludedFromReorderNotificationMail;
    }

    public function setIsExcludedFromReorderNotificationMail(bool $isExcludedFromReorderNotificationMail): void
    {
        $this->isExcludedFromReorderNotificationMail = $isExcludedFromReorderNotificationMail;
    }

    public function getTrackingProfile(): ProductTrackingProfile
    {
        return $this->trackingProfile;
    }

    public function setTrackingProfile(ProductTrackingProfile $trackingProfile): void
    {
        $this->trackingProfile = $trackingProfile;
    }

    public function getReservedStock(): int
    {
        return $this->reservedStock;
    }

    public function setReservedStock(int $reservedStock): void
    {
        $this->reservedStock = $reservedStock;
    }

    public function getInternalReservedStock(): int
    {
        return $this->internalReservedStock;
    }

    public function setInternalReservedStock(int $internalReservedStock): void
    {
        $this->internalReservedStock = $internalReservedStock;
    }

    public function getExternalReservedStock(): int
    {
        return $this->externalReservedStock;
    }

    public function setExternalReservedStock(int $externalReservedStock): void
    {
        $this->externalReservedStock = $externalReservedStock;
    }

    public function getPhysicalStock(): int
    {
        return $this->physicalStock;
    }

    public function setPhysicalStock(int $physicalStock): void
    {
        $this->physicalStock = $physicalStock;
    }

    public function getStockNotAvailableForSale(): int
    {
        return $this->stockNotAvailableForSale;
    }

    public function setStockNotAvailableForSale(int $stockNotAvailableForSale): void
    {
        $this->stockNotAvailableForSale = $stockNotAvailableForSale;
    }

    public function getShipAutomatically(): bool
    {
        return $this->shipAutomatically;
    }

    public function setShipAutomatically(bool $shipAutomatically): void
    {
        $this->shipAutomatically = $shipAutomatically;
    }

    public function getDefaultSupplierId(): ?string
    {
        return $this->defaultSupplierId;
    }

    public function setDefaultSupplierId(string $supplierId): void
    {
        if ($this->defaultSupplier?->getId() !== $supplierId) {
            $this->defaultSupplier = null;
        }
        $this->defaultSupplierId = $supplierId;
    }

    public function getDefaultSupplier(): ?SupplierEntity
    {
        if ($this->defaultSupplierId && !$this->defaultSupplier) {
            throw new AssociationNotLoadedException('defaultSupplier', $this);
        }

        return $this->defaultSupplier;
    }

    public function setDefaultSupplier(?SupplierEntity $supplier): void
    {
        if ($supplier) {
            $this->defaultSupplierId = $supplier->getId();
        }
        $this->defaultSupplier = $supplier;
    }
}
