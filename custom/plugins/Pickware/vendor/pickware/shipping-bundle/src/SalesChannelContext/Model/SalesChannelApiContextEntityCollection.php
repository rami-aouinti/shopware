<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\SalesChannelContext\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(SalesChannelApiContextEntity $entity)
 * @method void set(string $key, SalesChannelApiContextEntity $entity)
 * @method SalesChannelApiContextEntity[] getIterator()
 * @method SalesChannelApiContextEntity[] getElements()
 * @method SalesChannelApiContextEntity|null get(string $key)
 * @method SalesChannelApiContextEntity|null first()
 * @method SalesChannelApiContextEntity|null last()
 */
class SalesChannelApiContextEntityCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SalesChannelApiContextEntity::class;
    }
}
