<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Statistic\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(PickEventUserRoleEntity $entity)
 * @method void set(string $key, PickEventUserRoleEntity $entity)
 * @method PickEventUserRoleEntity[] getIterator()
 * @method PickEventUserRoleEntity[] getElements()
 * @method PickEventUserRoleEntity|null get(string $key)
 * @method PickEventUserRoleEntity|null first()
 * @method PickEventUserRoleEntity|null last()
 *
 * @extends EntityCollection<PickEventUserRoleEntity>
 */
class PickEventUserRoleCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PickEventUserRoleEntity::class;
    }
}
