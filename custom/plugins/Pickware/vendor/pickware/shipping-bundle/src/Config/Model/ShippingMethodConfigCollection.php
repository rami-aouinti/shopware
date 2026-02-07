<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Config\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void             add(ShippingMethodConfigEntity $entity)
 * @method void             set(string $key, ShippingMethodConfigEntity $entity)
 * @method ShippingMethodConfigEntity[]    getIterator()
 * @method ShippingMethodConfigEntity[]    getElements()
 * @method ShippingMethodConfigEntity|null get(string $key)
 * @method ShippingMethodConfigEntity|null first()
 * @method ShippingMethodConfigEntity|null last()
 */
class ShippingMethodConfigCollection extends EntityCollection {}
