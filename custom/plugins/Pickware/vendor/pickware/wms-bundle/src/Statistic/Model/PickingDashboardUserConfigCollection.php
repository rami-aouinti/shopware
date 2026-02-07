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
 * @method void add(PickingDashboardUserConfigEntity $entity)
 * @method void set(string $key, PickingDashboardUserConfigEntity $entity)
 * @method PickingDashboardUserConfigEntity[] getIterator()
 * @method PickingDashboardUserConfigEntity[] getElements()
 * @method PickingDashboardUserConfigEntity|null get(string $key)
 * @method PickingDashboardUserConfigEntity|null first()
 * @method PickingDashboardUserConfigEntity|null last()
 *
 * @extends EntityCollection<PickingDashboardUserConfigEntity>
 */
class PickingDashboardUserConfigCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PickingDashboardUserConfigEntity::class;
    }
}
