<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\Model;

use Pickware\DalBundle\Association\Exception\AssociationNotLoadedException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\Tag\TagEntity;

class SupplierOrderTagEntity extends Entity
{
    protected string $supplierOrderId;
    protected string $tagId;
    protected ?SupplierOrderEntity $supplierOrder = null;
    protected ?TagEntity $tag = null;

    public function getSupplierOrderId(): string
    {
        return $this->supplierOrderId;
    }

    public function setSupplierOrderId(string $supplierOrderId): void
    {
        if ($this->supplierOrder && $this->supplierOrder->getId() !== $supplierOrderId) {
            $this->supplierOrder = null;
        }
        $this->supplierOrderId = $supplierOrderId;
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

    public function getSupplierOrder(): SupplierOrderEntity
    {
        if ($this->supplierOrder === null) {
            throw new AssociationNotLoadedException('supplierOrder', $this);
        }

        return $this->supplierOrder;
    }

    public function setSupplierOrder(SupplierOrderEntity $supplierOrder): void
    {
        $this->supplierOrder = $supplierOrder;
        $this->supplierOrderId = $supplierOrder->getId();
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
