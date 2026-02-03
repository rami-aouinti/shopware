<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\EntryBatch\DependencyInjection;

use Pickware\DatevBundle\EntryBatch\EntityIdChunkCalculator;
use Pickware\PickwareErpStarter\Registry\AbstractRegistry;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class EntityIdChunkCalculatorRegistry extends AbstractRegistry
{
    public const DI_CONTAINER_TAG = 'pickware_datev.entry_batch.entity_id_chunk_calculator';

    public function __construct(
        #[AutowireIterator(self::DI_CONTAINER_TAG)]
        iterable $entityIdBatchCalculator,
    ) {
        parent::__construct(
            $entityIdBatchCalculator,
            [EntityIdChunkCalculator::class],
            self::DI_CONTAINER_TAG,
        );
    }

    /**
     * @param EntityIdChunkCalculator $instance
     */
    protected function getKey($instance): string
    {
        return $instance->getEntryBatchRecordCreatorTechnicalName();
    }

    public function getEntityIdChunkCalculatorByEntryBatchRecordCreatorTechnicalName(string $technicalName): EntityIdChunkCalculator
    {
        return $this->getRegisteredInstanceByKey($technicalName);
    }
}
