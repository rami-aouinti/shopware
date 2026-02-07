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
 * @method void             add(ShipmentEntity $entity)
 * @method void             set(string $key, ShipmentEntity $entity)
 * @method ShipmentEntity[]    getIterator()
 * @method ShipmentEntity[]    getElements()
 * @method ShipmentEntity|null get(string $key)
 * @method ShipmentEntity|null first()
 * @method ShipmentEntity|null last()
 */
class ShipmentCollection extends EntityCollection {}
