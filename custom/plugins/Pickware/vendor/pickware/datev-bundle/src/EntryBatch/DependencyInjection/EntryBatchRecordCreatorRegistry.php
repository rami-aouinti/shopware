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

use Pickware\DatevBundle\EntryBatch\EntryBatchRecordCreator;
use Pickware\PickwareErpStarter\Registry\AbstractRegistry;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class EntryBatchRecordCreatorRegistry extends AbstractRegistry
{
    public const DI_CONTAINER_TAG = 'pickware_datev.entry_batch.entry_batch_record_creator';

    public function __construct(
        #[AutowireIterator(self::DI_CONTAINER_TAG)]
        iterable $entryBatchRecordCreators,
    ) {
        parent::__construct(
            $entryBatchRecordCreators,
            [EntryBatchRecordCreator::class],
            self::DI_CONTAINER_TAG,
        );
    }

    /**
     * @param EntryBatchRecordCreator $instance
     */
    protected function getKey($instance): string
    {
        return $instance->getTechnicalName();
    }

    public function getEntryBatchRecordCreatorByTechnicalName(string $technicalName): EntryBatchRecordCreator
    {
        return $this->getRegisteredInstanceByKey($technicalName);
    }
}
