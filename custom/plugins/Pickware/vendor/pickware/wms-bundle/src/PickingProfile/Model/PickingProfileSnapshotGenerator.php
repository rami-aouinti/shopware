<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProfile\Model;

use Pickware\DalBundle\EntityManager;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\AsEntitySnapshotGenerator;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\EntitySnapshotGenerator;
use Shopware\Core\Framework\Context;

/**
 * @phpstan-type PickingProfileSnapshot array{name: string}
 * @implements EntitySnapshotGenerator<PickingProfileSnapshot>
 */
#[AsEntitySnapshotGenerator(entityClass: PickingProfileDefinition::class)]
class PickingProfileSnapshotGenerator implements EntitySnapshotGenerator
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function generateSnapshots(array $ids, Context $context): array
    {
        return $this->entityManager->findBy(
            PickingProfileDefinition::class,
            ['id' => $ids],
            $context,
        )->map(fn(PickingProfileEntity $pickingProfile) => [
            'name' => $pickingProfile->getName(),
        ]);
    }
}
