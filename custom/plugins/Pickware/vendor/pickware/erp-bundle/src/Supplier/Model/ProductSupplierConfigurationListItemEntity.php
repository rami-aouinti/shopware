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
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ProductSupplierConfigurationListItemEntity extends Entity
{
    use EntityIdTrait;

    protected string $productId;
    protected ?string $productVersionId;
    protected ?ProductEntity $product = null;
    protected ?string $productSupplierConfigurationId;
    protected ?ProductSupplierConfigurationEntity $productSupplierConfiguration = null;

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

    public function getProductSupplierConfigurationId(): ?string
    {
        return $this->productSupplierConfigurationId;
    }

    public function setProductSupplierConfigurationId(?string $productSupplierConfigurationId): void
    {
        if ($this->productSupplierConfiguration && $this->productSupplierConfiguration->getId() !== $productSupplierConfigurationId) {
            $this->productSupplierConfiguration = null;
        }
        $this->productSupplierConfigurationId = $productSupplierConfigurationId;
    }

    public function getProductSupplierConfiguration(): ?ProductSupplierConfigurationEntity
    {
        if ($this->productSupplierConfigurationId && !$this->productSupplierConfiguration) {
            throw new AssociationNotLoadedException('productSupplierConfiguration', $this);
        }

        return $this->productSupplierConfiguration;
    }

    public function setProductSupplierConfiguration(?ProductSupplierConfigurationEntity $productSupplierConfiguration): void
    {
        $this->productSupplierConfigurationId = $productSupplierConfiguration?->getId();
        $this->productSupplierConfiguration = $productSupplierConfiguration;
    }
}
