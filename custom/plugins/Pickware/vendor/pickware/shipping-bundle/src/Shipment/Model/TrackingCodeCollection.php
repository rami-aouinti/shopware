<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void             add(TrackingCodeEntity $entity)
 * @method void             set(string $key, TrackingCodeEntity $entity)
 * @method TrackingCodeEntity[]    getIterator()
 * @method TrackingCodeEntity[]    getElements()
 * @method TrackingCodeEntity|null get(string $key)
 * @method TrackingCodeEntity|null first()
 * @method TrackingCodeEntity|null last()
 */
class TrackingCodeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return TrackingCodeEntity::class;
    }
}
