<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration;

use Attribute;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AsEntitySnapshotGenerator extends AutoconfigureTag
{
    public const TAG_NAME = 'pickware.shopware_extensions.entity_snapshot_generator';

    /**
     * @param class-string<EntityDefinition<Entity>> $entityClass
     */
    public function __construct(
        string $entityClass,
    ) {
        parent::__construct(
            name: self::TAG_NAME,
            attributes: ['entityClass' => $entityClass],
        );
    }
}
