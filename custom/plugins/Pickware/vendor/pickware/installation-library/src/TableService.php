<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

class TableService
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function tableExists(string $tableName): bool
    {
        $functionName = 'tablesExist';
        if (method_exists(AbstractSchemaManager::class, 'tableExists')) {
            $functionName = 'tableExists';
        }

        return $this->db->createSchemaManager()->$functionName($tableName);
    }
}
