<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\ShippingProcess\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(ShippingProcessEntity $entity)
 * @method void set(string $key, ShippingProcessEntity $entity)
 * @method ShippingProcessEntity[] getIterator()
 * @method ShippingProcessEntity[] getElements()
 * @method ShippingProcessEntity|null get(string $key)
 * @method ShippingProcessEntity|null first()
 * @method ShippingProcessEntity|null last()
 *
 * @extends EntityCollection<ShippingProcessEntity>
 */
class ShippingProcessCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ShippingProcessEntity::class;
    }
}
