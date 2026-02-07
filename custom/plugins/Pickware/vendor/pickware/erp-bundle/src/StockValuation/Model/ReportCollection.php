<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockValuation\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @implements EntityCollection<ReportEntity>
 */
class ReportCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ReportEntity::class;
    }
}
