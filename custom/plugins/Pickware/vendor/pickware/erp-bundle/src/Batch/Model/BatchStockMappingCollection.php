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

use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PickwareErpStarter\Batch\ImmutableBatchQuantityMap;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BatchStockMappingEntity>
 */
class BatchStockMappingCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return BatchStockMappingEntity::class;
    }

    /**
     * @return CountingMap<string> a map containing the total quantities for each batch in this collection
     */
    public function asBatchCountingMap(): CountingMap
    {
        $countingMap = new CountingMap();
        foreach ($this->getElements() as $batchStockMapping) {
            $countingMap->add($batchStockMapping->getBatchId(), $batchStockMapping->getQuantity());
        }

        return $countingMap;
    }

    public function asBatchQuantityMap(): ImmutableBatchQuantityMap
    {
        return new ImmutableBatchQuantityMap($this->asBatchCountingMap()->asArray());
    }
}
