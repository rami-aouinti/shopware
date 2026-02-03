<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\Migration;

use Doctrine\DBAL\Connection;

/**
 * Drops an index from a table if it exists. This function safely checks for the existence of an index
 * before attempting to drop it, preventing errors when the index might not exist.
 */
function dropIndexIfExists(Connection $connection, string $tableName, string $indexName): void
{
    // Check if the index exists before attempting to drop it
    $indexExists = $connection->fetchOne(
        'SELECT 1
        FROM `INFORMATION_SCHEMA`.`STATISTICS`
        WHERE
            `TABLE_NAME` = ?
            AND `INDEX_NAME` = ?
            AND `TABLE_SCHEMA` = DATABASE()',
        [
            $tableName,
            $indexName,
        ],
    );

    if ($indexExists) {
        $connection->executeStatement(
            sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $tableName, $indexName),
        );
    }
}
