<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Pickware\PhpStandardLibrary\DateTime\CalendarDate;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemCollection;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\UserSnapshotGenerator;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\User\UserEntity;

/**
 * @phpstan-import-type UserSnapshot from UserSnapshotGenerator
 * @phpstan-type CustomFieldsArray array<string, string>
 */
class BatchEntity extends Entity
{
    use EntityIdTrait;

    protected string $productId;
    protected ?string $productVersionId;
    protected ?ProductEntity $product = null;
    protected ?string $number;
    protected ?string $comment = null;
    protected ?CalendarDate $productionDate = null;
    protected ?CalendarDate $bestBeforeDate = null;
    protected int $physicalStock;
    protected ?string $userId;
    protected ?UserEntity $user = null;

    /** @var UserSnapshot|null $userSnapshot */
    protected ?array $userSnapshot;

    /** @var CustomFieldsArray $customFields */
    protected array $customFields;

    protected ?BatchStockMappingCollection $stockMappings = null;
    protected ?BatchStockMovementMappingCollection $stockMovementMappings = null;
    protected ?GoodsReceiptLineItemCollection $goodsReceiptLineItems = null;
    protected ?TagCollection $tags = null;

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

    public function setProduct(ProductEntity $product): void
    {
        $this->product = $product;
        $this->productId = $product->getId();
        $this->productVersionId = $product->getVersionId();
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): void
    {
        $this->number = $number;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getProductionDate(): ?CalendarDate
    {
        return $this->productionDate;
    }

    public function setProductionDate(?CalendarDate $productionDate): void
    {
        $this->productionDate = $productionDate;
    }

    public function getBestBeforeDate(): ?CalendarDate
    {
        return $this->bestBeforeDate;
    }

    public function setBestBeforeDate(?CalendarDate $bestBeforeDate): void
    {
        $this->bestBeforeDate = $bestBeforeDate;
    }

    public function getPhysicalStock(): int
    {
        return $this->physicalStock;
    }

    public function setPhysicalStock(int $physicalStock): void
    {
        $this->physicalStock = $physicalStock;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        if ($this->user && $this->user->getId() !== $userId) {
            $this->user = null;
        }
        $this->userId = $userId;
    }

    public function getUser(): ?UserEntity
    {
        if (!$this->user && $this->userId) {
            throw new AssociationNotLoadedException('user', $this);
        }

        return $this->user;
    }

    public function setUser(?UserEntity $user): void
    {
        $this->user = $user;
        $this->userId = $user?->getId();
    }

    /**
     * @return UserSnapshot|null
     */
    public function getUserSnapshot(): ?array
    {
        return $this->userSnapshot;
    }

    /**
     * @param UserSnapshot|null $userSnapshot
     */
    public function setUserSnapshot(?array $userSnapshot): void
    {
        $this->userSnapshot = $userSnapshot;
    }

    /**
     * @return CustomFieldsArray
     */
    public function getCustomFields(): array
    {
        return $this->customFields;
    }

    /**
     * @param CustomFieldsArray $customFields
     */
    public function setCustomFields(array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getStockMappings(): BatchStockMappingCollection
    {
        if ($this->stockMappings === null) {
            throw new AssociationNotLoadedException('stockBatchMappings', $this);
        }

        return $this->stockMappings;
    }

    public function setStockMappings(?BatchStockMappingCollection $stockMappings): void
    {
        $this->stockMappings = $stockMappings;
    }

    public function getStockMovementMappings(): BatchStockMovementMappingCollection
    {
        if ($this->stockMovementMappings === null) {
            throw new AssociationNotLoadedException('stockMovementBatches', $this);
        }

        return $this->stockMovementMappings;
    }

    public function setStockMovementMappings(?BatchStockMovementMappingCollection $stockMovementMappings): void
    {
        $this->stockMovementMappings = $stockMovementMappings;
    }

    public function getGoodsReceiptLineItems(): GoodsReceiptLineItemCollection
    {
        if ($this->goodsReceiptLineItems === null) {
            throw new AssociationNotLoadedException('goodsReceiptLineItems', $this);
        }

        return $this->goodsReceiptLineItems;
    }

    public function setGoodsReceiptLineItems(?GoodsReceiptLineItemCollection $goodsReceiptLineItems): void
    {
        $this->goodsReceiptLineItems = $goodsReceiptLineItems;
    }

    public function getTags(): TagCollection
    {
        if ($this->tags === null) {
            throw new AssociationNotLoadedException('tags', $this);
        }

        return $this->tags;
    }

    public function setTags(?TagCollection $tags): void
    {
        $this->tags = $tags;
    }
}
