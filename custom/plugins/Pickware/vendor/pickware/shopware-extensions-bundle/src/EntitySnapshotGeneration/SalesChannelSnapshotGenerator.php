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
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @phpstan-type SalesChannelSnapshot array{name: ?string, shortName: ?string}
 * @implements EntitySnapshotGenerator<SalesChannelSnapshot>
 */
#[AsEntitySnapshotGenerator(entityClass: SalesChannelDefinition::class)]
class SalesChannelSnapshotGenerator implements EntitySnapshotGenerator
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function generateSnapshots(array $ids, Context $context): array
    {
        return $this->entityManager->findBy(
            SalesChannelDefinition::class,
            ['id' => $ids],
            $context,
        )->map(fn(SalesChannelEntity $salesChannel) => [
            'name' => $salesChannel->getName(),
            'shortName' => $salesChannel->getShortName(),
        ]);
    }
}
