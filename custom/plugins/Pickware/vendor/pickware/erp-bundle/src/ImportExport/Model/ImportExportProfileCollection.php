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
 * @method void add(ImportExportProfileEntity $entity)
 * @method void set(string $key, ImportExportProfileEntity $entity)
 * @method ImportExportProfileEntity[] getIterator()
 * @method ImportExportProfileEntity[] getElements()
 * @method ImportExportProfileEntity|null get(string $key)
 * @method ImportExportProfileEntity|null first()
 * @method ImportExportProfileEntity|null last()
 * @extends EntityCollection<ImportExportProfileEntity>
 */
class ImportExportProfileCollection extends EntityCollection {}
