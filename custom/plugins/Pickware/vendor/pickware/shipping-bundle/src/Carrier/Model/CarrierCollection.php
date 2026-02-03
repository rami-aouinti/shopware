<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Carrier\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void             add(CarrierEntity $entity)
 * @method void             set(string $key, CarrierEntity $entity)
 * @method CarrierEntity[]    getIterator()
 * @method CarrierEntity[]    getElements()
 * @method CarrierEntity|null get(string $key)
 * @method CarrierEntity|null first()
 * @method CarrierEntity|null last()
 */
class CarrierCollection extends EntityCollection {}
