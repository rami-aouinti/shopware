<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemandPlanning\AnalyticsProfile\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(DemandPlanningListItemEntity $entity)
 * @method void set(string $key, DemandPlanningListItemEntity $entity)
 * @method DemandPlanningListItemEntity[] getIterator()
 * @method DemandPlanningListItemEntity[] getElements()
 * @method DemandPlanningListItemEntity|null get(string $key)
 * @method DemandPlanningListItemEntity|null first()
 * @method DemandPlanningListItemEntity|null last()
 */
class DemandPlanningListItemCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return DemandPlanningListItemEntity::class;
    }
}
