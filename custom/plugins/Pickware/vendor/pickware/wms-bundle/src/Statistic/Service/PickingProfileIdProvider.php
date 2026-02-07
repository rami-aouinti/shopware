<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Statistic\Service;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareWms\Statistic\Model\PickingProcessLifecycleEventDefinition;
use Pickware\PickwareWms\Statistic\Model\PickingProcessLifecycleEventEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class PickingProfileIdProvider
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function findLastUsedPickingProfileIdForPickingProcess(
        string $pickingProcessId,
        Context $context,
    ): ?string {
        /** @var ?PickingProcessLifecycleEventEntity $pickingProcessLifecycleEvent */
        $pickingProcessLifecycleEvent = $this->entityManager->findFirstBy(
            PickingProcessLifecycleEventDefinition::class,
            new FieldSorting('eventCreatedAt', FieldSorting::DESCENDING),
            $context,
            ['pickingProcessReferenceId' => $pickingProcessId],
        );

        return $pickingProcessLifecycleEvent?->getPickingProfileReferenceId();
    }
}
