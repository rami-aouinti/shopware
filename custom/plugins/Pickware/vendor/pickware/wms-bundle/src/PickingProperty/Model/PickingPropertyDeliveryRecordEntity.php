<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProperty\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PickingPropertyDeliveryRecordEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $productId = null;
    protected ?string $productVersionId = null;
    protected ?ProductEntity $product = null;
    protected array $productSnapshot;
    protected string $deliveryId;
    protected ?DeliveryEntity $delivery = null;
    protected ?PickingPropertyDeliveryRecordValueCollection $values = null;

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): void
    {
        if ($this->product?->getId() !== $productId) {
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
        if ($this->product?->getVersionId() !== $productVersionId) {
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

    public function getProductSnapshot(): array
    {
        return $this->productSnapshot;
    }

    public function setProductSnapshot(array $productSnapshot): void
    {
        $this->productSnapshot = $productSnapshot;
    }

    public function getDeliveryId(): string
    {
        return $this->deliveryId;
    }

    public function setDeliveryId(string $deliveryId): void
    {
        if ($this->delivery?->getId() !== $deliveryId) {
            $this->delivery = null;
        }

        $this->deliveryId = $deliveryId;
    }

    public function getDelivery(): DeliveryEntity
    {
        if (!$this->delivery) {
            throw new AssociationNotLoadedException('delivery', $this);
        }

        return $this->delivery;
    }

    public function setDelivery(DeliveryEntity $delivery): void
    {
        $this->delivery = $delivery;
        $this->deliveryId = $delivery->getId();
    }

    public function getValues(): PickingPropertyDeliveryRecordValueCollection
    {
        if (!$this->values) {
            throw new AssociationNotLoadedException('values', $this);
        }

        return $this->values;
    }

    public function setValues(PickingPropertyDeliveryRecordValueCollection $values): void
    {
        $this->values = $values;
    }
}
