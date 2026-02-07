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
use InvalidArgumentException;

/**
 * Sets the character set and collation of a column in a table. Use this function if you're about to create a foreign
 * key targeting a column with type "varchar" to ensure that the target column has the correct character set and
 * collation.
 */
function ensureCorrectCollationOfColumnForForeignKeyConstraint(
    Connection $connection,
    string $tableName,
    string $columnName,
): void {
    $columnInfo = $connection->fetchAssociative(
        'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, GENERATION_EXPRESSION FROM information_schema.COLUMNS
               WHERE TABLE_NAME = :tableName
                 AND COLUMN_NAME = :columnName
                 AND TABLE_SCHEMA = DATABASE()',
        [
            'tableName' => $tableName,
            'columnName' => $columnName,
        ],
    );

    // COLUMN_DEFAULT is null in MySQL and 'NULL' in MariaDB
    if ($columnInfo['COLUMN_DEFAULT'] !== null && $columnInfo['COLUMN_DEFAULT'] !== 'NULL') {
        throw new InvalidArgumentException('Cannot set collation for columns with a default value');
    }

    // GENERATION_EXPRESSION is '' in MySQL and NULL in MariaDB
    if ($columnInfo['GENERATION_EXPRESSION'] !== '' && $columnInfo['GENERATION_EXPRESSION'] !== null) {
        throw new InvalidArgumentException('Cannot set collation for columns with a generation expression');
    }

    $nullability = $columnInfo['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL';

    $connection->executeStatement(
        // Note that this cannot use named parameters since they will be quoted using single quotes, resulting in
        // invalid SQL syntax
        sprintf(
            'ALTER TABLE %s MODIFY %s %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci %s',
            $tableName,
            $columnName,
            $columnInfo['COLUMN_TYPE'],
            $nullability,
        ),
    );
}
