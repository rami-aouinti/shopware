<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Address\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(AddressEntity $entity)
 * @method void set(string $key, AddressEntity $entity)
 * @method AddressEntity[] getIterator()
 * @method AddressEntity[] getElements()
 * @method AddressEntity|null get(string $key)
 * @method AddressEntity|null first()
 * @method AddressEntity|null last()
 */
class AddressCollection extends EntityCollection {}
