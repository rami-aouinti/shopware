<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\IdResolver\EntityIdResolver as BaseIdResolver;

/**
 * @deprecated next-major will be removed in v6.0.0. Use Pickware\DalBundle\IdResolver\EntityIdResolver instead
 */
class EntityIdResolver extends BaseIdResolver
{
    public function __construct(Connection $connection)
    {
        parent::__construct($connection);
    }
}
