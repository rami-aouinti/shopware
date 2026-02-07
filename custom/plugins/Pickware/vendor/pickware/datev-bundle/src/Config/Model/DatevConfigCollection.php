<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\Model;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @method void add(DatevConfigEntity $entity)
 * @method void set(string $key, DatevConfigEntity $entity)
 * @method DatevConfigEntity[] getIterator()
 * @method DatevConfigEntity[] getElements()
 * @method DatevConfigEntity|null get(string $key)
 * @method DatevConfigEntity|null first()
 * @method DatevConfigEntity|null last()
 */
#[Exclude]
class DatevConfigCollection extends EntityCollection {}
