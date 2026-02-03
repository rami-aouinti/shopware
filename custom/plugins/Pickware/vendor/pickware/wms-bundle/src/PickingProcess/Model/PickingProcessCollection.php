<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(PickingProcessEntity $entity)
 * @method void set(string $key, PickingProcessEntity $entity)
 * @method PickingProcessEntity[] getIterator()
 * @method PickingProcessEntity[] getElements()
 * @method PickingProcessEntity|null get(string $key)
 * @method PickingProcessEntity|null first()
 * @method PickingProcessEntity|null last()
 *
 * @extends EntityCollection<PickingProcessEntity>
 */
class PickingProcessCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PickingProcessEntity::class;
    }
}
