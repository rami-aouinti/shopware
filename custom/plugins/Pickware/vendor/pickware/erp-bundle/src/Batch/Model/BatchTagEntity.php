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
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\Tag\TagEntity;

class BatchTagEntity extends Entity
{
    protected string $batchId;
    protected string $tagId;
    protected ?BatchEntity $batch = null;
    protected ?TagEntity $tag = null;

    public function getBatchId(): string
    {
        return $this->batchId;
    }

    public function setBatchId(string $batchId): void
    {
        if ($this->batch && $this->batch->getId() !== $batchId) {
            $this->batch = null;
        }
        $this->batchId = $batchId;
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

    public function getBatch(): BatchEntity
    {
        if (!$this->batch) {
            throw new AssociationNotLoadedException('batch', $this);
        }

        return $this->batch;
    }

    public function setBatch(BatchEntity $batch): void
    {
        $this->batch = $batch;
        $this->batchId = $batch->getId();
    }

    public function getTag(): TagEntity
    {
        if (!$this->tag) {
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
