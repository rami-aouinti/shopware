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

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Context;

/**
 * @phpstan-type AclRoleSnapshot array{name: string}
 * @implements EntitySnapshotGenerator<AclRoleSnapshot>
 */
#[AsEntitySnapshotGenerator(entityClass: AclRoleDefinition::class)]
class AclRoleSnapshotGenerator implements EntitySnapshotGenerator
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function generateSnapshots(array $ids, Context $context): array
    {
        return $this->entityManager->findBy(
            AclRoleDefinition::class,
            ['id' => $ids],
            $context,
        )->map(fn(AclRoleEntity $role) => [
            'name' => $role->getName(),
        ]);
    }
}
