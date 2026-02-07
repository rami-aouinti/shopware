<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class EntityCollectionExtension
{
    /**
     * Returns the value of the field $fieldName of each entity in $entityCollection as numeric array.
     */
    public static function getField(EntityCollection $entityCollection, string $fieldName): array
    {
        return array_values($entityCollection->map(fn(Entity $entity) => $entity->get($fieldName)));
    }
}
