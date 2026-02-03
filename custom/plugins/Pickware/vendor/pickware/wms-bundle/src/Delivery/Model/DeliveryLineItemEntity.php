<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class DeliveryLineItemEntity extends Entity
{
    use EntityIdTrait;

    protected string $deliveryId;
    protected ?DeliveryEntity $delivery = null;
    protected string $productId;
    protected string $productVersionId;
    protected ?ProductEntity $product = null;
    protected int $quantity;

    public function getDeliveryId(): string
    {
        return $this->deliveryId;
    }

    public function setDeliveryId(string $deliveryId): void
    {
        if ($this->delivery && $this->delivery->getId() !== $deliveryId) {
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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }
}
