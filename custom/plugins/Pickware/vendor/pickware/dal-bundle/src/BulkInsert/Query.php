<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle\BulkInsert;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Identifier;

/**
 * This is a copy of https://github.com/franzose/doctrine-bulk-insert
 */
class Query
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function execute(string $table, array $dataset, array $types = []): int
    {
        if (empty($dataset)) {
            return 0;
        }

        $sql = sql($this->connection->getDatabasePlatform(), new Identifier($table), $dataset);

        if (method_exists($this->connection, 'executeStatement')) {
            return $this->connection->executeStatement($sql, parameters($dataset), types($types, count($dataset)));
        }

        return $this->connection->executeUpdate($sql, parameters($dataset), types($types, count($dataset)));
    }

    public function transactional(string $table, array $dataset, array $types = []): int
    {
        return $this->connection->transactional(fn() => $this->execute($table, $dataset, $types));
    }
}
