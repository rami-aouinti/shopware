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

use Shopware\Core\Framework\Context;

interface EntityIdChunkCalculator
{
    public function getEntityIdCountForExportConfig(array $exportConfig, Context $context): EntityIdCount;

    /**
     * @return string[]
     */
    public function getEntityIdChunkForExport(string $exportId, int $chunkSize, int $offset, Context $context): array;

    public function getEntryBatchRecordCreatorTechnicalName(): string;
}
