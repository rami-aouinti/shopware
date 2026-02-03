<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BatchTagEntity>
 */
class BatchTagCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'pickware_erp_batch_tag_collection';
    }

    protected function getExpectedClass(): string
    {
        return BatchTagEntity::class;
    }
}
