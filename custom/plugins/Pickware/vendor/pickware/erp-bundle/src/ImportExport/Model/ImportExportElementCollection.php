<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ImportExportElementEntity>
 * @method void add(ImportExportElementEntity $entity)
 * @method void set(string $key, ImportExportElementEntity $entity)
 * @method ImportExportElementEntity[] getIterator()
 * @method ImportExportElementEntity[] getElements()
 * @method ImportExportElementEntity|null get(string $key)
 * @method ImportExportElementEntity|null first()
 * @method ImportExportElementEntity|null last()
 */
class ImportExportElementCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ImportExportElementEntity::class;
    }
}
