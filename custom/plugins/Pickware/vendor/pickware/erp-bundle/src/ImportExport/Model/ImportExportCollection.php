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
 * @method void add(ImportExportEntity $entity)
 * @method void set(string $key, ImportExportEntity $entity)
 * @method ImportExportEntity[] getIterator()
 * @method ImportExportEntity[] getElements()
 * @method ImportExportEntity|null get(string $key)
 * @method ImportExportEntity|null first()
 * @method ImportExportEntity|null last()
 */
class ImportExportCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ImportExportEntity::class;
    }
}
