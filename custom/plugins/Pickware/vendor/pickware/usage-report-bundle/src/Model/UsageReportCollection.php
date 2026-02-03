<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(UsageReportEntity $entity)
 * @method void set(string $key, UsageReportEntity $entity)
 * @method UsageReportEntity[] getIterator()
 * @method UsageReportEntity[] getElements()
 * @method UsageReportEntity|null get(string $key)
 * @method UsageReportEntity|null first()
 * @method UsageReportEntity|null last()
 *
 * @extends EntityCollection<UsageReportEntity>
 */
class UsageReportCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return UsageReportEntity::class;
    }
}
