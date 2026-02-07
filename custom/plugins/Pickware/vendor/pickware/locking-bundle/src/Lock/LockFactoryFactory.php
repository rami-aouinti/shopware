<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LockingBundle\Lock;

use Doctrine\DBAL\Connection;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\DoctrineDbalStore;

class LockFactoryFactory
{
    public const TABLE_NAME = 'pickware_locking_lock';

    public function __construct(
        private readonly Connection $connection,
    ) {}

    // Use this method in the autowiring expression when injecting a LockFactory
    public function getLockFactory(): LockFactory
    {
        $store = new DoctrineDbalStore(
            connOrUrl: $this->connection,
            options: [
                'db_table' => self::TABLE_NAME,
            ],
        );

        return new LockFactory($store);
    }
}
