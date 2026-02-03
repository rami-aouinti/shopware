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
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ProductStockLocationConfigurationEntity extends Entity
{
    use EntityIdTrait;

    protected string $productStockLocationMappingId;
    protected ?ProductStockLocationMappingEntity $productStockLocationMapping = null;
    protected int $reorderPoint;
    protected ?int $targetMaximumQuantity = null;
    protected ?int $stockBelowReorderPoint = null;

    public function getProductStockLocationMappingId(): string
    {
        return $this->productStockLocationMappingId;
    }

    public function setProductStockLocationMappingId(string $productStockLocationMappingId): void
    {
        if ($this->productStockLocationMapping && $this->productStockLocationMapping->getId() !== $productStockLocationMappingId) {
            $this->productStockLocationMapping = null;
        }
        $this->productStockLocationMappingId = $productStockLocationMappingId;
    }

    public function getProductStockLocationMapping(): ProductStockLocationMappingEntity
    {
        if (!$this->productStockLocationMapping && $this->productStockLocationMappingId) {
            throw new AssociationNotLoadedException('productStockLocationMapping', $this);
        }

        return $this->productStockLocationMapping;
    }

    public function setProductStockLocationMapping(ProductStockLocationMappingEntity $productStockLocationMapping): void
    {
        $this->productStockLocationMappingId = $productStockLocationMapping->getId();
        $this->productStockLocationMapping = $productStockLocationMapping;
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
}
