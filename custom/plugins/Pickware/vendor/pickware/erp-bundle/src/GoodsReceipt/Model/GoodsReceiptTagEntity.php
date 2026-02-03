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
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\Tag\TagEntity;

class GoodsReceiptTagEntity extends Entity
{
    protected string $goodsReceiptId;
    protected string $tagId;
    protected ?GoodsReceiptEntity $goodsReceipt = null;
    protected ?TagEntity $tag = null;

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

    public function getTagId(): string
    {
        return $this->tagId;
    }

    public function setTagId(string $tagId): void
    {
        if ($this->tag && $this->tag->getId() !== $tagId) {
            $this->tag = null;
        }
        $this->tagId = $tagId;
    }

    public function getGoodsReceipt(): GoodsReceiptEntity
    {
        if ($this->goodsReceipt === null) {
            throw new AssociationNotLoadedException('goodsReceipt', $this);
        }

        return $this->goodsReceipt;
    }

    public function setGoodsReceipt(GoodsReceiptEntity $goodsReceipt): void
    {
        $this->goodsReceipt = $goodsReceipt;
        $this->goodsReceiptId = $goodsReceipt->getId();
    }

    public function getTag(): TagEntity
    {
        if ($this->tag === null) {
            throw new AssociationNotLoadedException('tag', $this);
        }

        return $this->tag;
    }

    public function setTag(TagEntity $tag): void
    {
        $this->tag = $tag;
        $this->tagId = $tag->getId();
    }
}
