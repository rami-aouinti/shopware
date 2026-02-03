<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ProductSetConfigurationEntity extends Entity
{
    use EntityIdTrait;

    protected string $productSetId;
    protected ?ProductSetEntity $productSet = null;
    protected string $productId;
    protected ?string $productVersionId;
    protected ?ProductEntity $product = null;
    protected int $quantity;

    public function getProductSetId(): string
    {
        return $this->productSetId;
    }

    public function setProductSetId(string $productSetId): void
    {
        if ($this->productSet && $this->productSet->getId() !== $productSetId) {
            $this->productSet = null;
        }
        $this->productSetId = $productSetId;
    }

    public function getProductSet(): ProductSetEntity
    {
        if (!$this->productSet) {
            throw new AssociationNotLoadedException('product', $this);
        }

        return $this->productSet;
    }

    public function setProductSet(?ProductSetEntity $productSet): void
    {
        if ($productSet) {
            $this->productSetId = $productSet->getId();
        }
        $this->productSet = $productSet;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        if ($this->productId && $this->product->getId() !== $productId) {
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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }
}
