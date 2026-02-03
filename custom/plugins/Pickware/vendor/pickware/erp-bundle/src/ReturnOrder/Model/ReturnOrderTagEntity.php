<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\Tag\TagEntity;

class ReturnOrderTagEntity extends Entity
{
    protected string $returnOrderId;
    protected ?string $returnOrderVersionId;
    protected string $tagId;
    protected ?ReturnOrderEntity $returnOrder = null;
    protected ?TagEntity $tag = null;

    public function getReturnOrderId(): string
    {
        return $this->returnOrderId;
    }

    public function setReturnOrderId(string $returnOrderId): void
    {
        if ($this->returnOrder && $this->returnOrder->getId() !== $returnOrderId) {
            $this->returnOrder = null;
        }
        $this->returnOrderId = $returnOrderId;
    }

    public function getReturnOrderVersionId(): ?string
    {
        return $this->returnOrderVersionId;
    }

    public function setReturnOrderVersionId(?string $returnOrderVersionId): void
    {
        if ($this->returnOrder && $this->returnOrder->getVersionId() !== $returnOrderVersionId) {
            $this->returnOrder = null;
        }
        $this->returnOrderVersionId = $returnOrderVersionId;
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

    public function getReturnOrder(): ReturnOrderEntity
    {
        if ($this->returnOrder === null) {
            throw new AssociationNotLoadedException('returnOrder', $this);
        }

        return $this->returnOrder;
    }

    public function setReturnOrder(ReturnOrderEntity $returnOrder): void
    {
        $this->returnOrder = $returnOrder;
        $this->returnOrderId = $returnOrder->getId();
        $this->returnOrderVersionId = $returnOrder->getVersionId();
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
