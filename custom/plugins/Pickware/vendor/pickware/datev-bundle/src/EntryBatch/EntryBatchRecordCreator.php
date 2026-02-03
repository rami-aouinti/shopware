<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\EntryBatch;

use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Shopware\Core\Framework\Context;

interface EntryBatchRecordCreator
{
    /**
     * @param string[] $entityIds The ids returned from this domains {@link EntityIdChunkCalculator}
     */
    public function createEntryBatchRecords(array $entityIds, array $exportConfig, string $exportId, Context $context): EntryBatchRecordCollection;

    public function getTechnicalName(): string;

    public function validateConfig(array $config): JsonApiErrors;
}
