<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PickingProperty\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PickingPropertyOrderRecordEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $productId = null;
    protected ?string $productVersionId = null;
    protected array $productSnapshot;
    protected ?ProductEntity $product = null;
    protected string $orderId;
    protected string $orderVersionId;
    protected ?OrderEntity $order = null;
    protected ?PickingPropertyOrderRecordValueCollection $values = null;

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

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        if ($this->order?->getId() !== $orderId) {
            $this->order = null;
        }

        $this->orderId = $orderId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(string $orderVersionId): void
    {
        if ($this->order?->getVersionId() !== $orderVersionId) {
            $this->order = null;
        }

        $this->orderVersionId = $orderVersionId;
    }

    public function getOrder(): OrderEntity
    {
        if (!$this->order) {
            throw new AssociationNotLoadedException('order', $this);
        }

        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
        $this->orderId = $order->getId();
        $this->orderVersionId = $order->getVersionId();
    }

    public function getValues(): PickingPropertyOrderRecordValueCollection
    {
        if (!$this->values) {
            throw new AssociationNotLoadedException('values', $this);
        }

        return $this->values;
    }

    public function setValues(PickingPropertyOrderRecordValueCollection $values): void
    {
        $this->values = $values;
    }
}
