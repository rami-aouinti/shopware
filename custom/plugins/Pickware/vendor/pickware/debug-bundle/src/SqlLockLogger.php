<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DebugBundle;

use DateTime;
use Doctrine\DBAL\Connection;

class SqlLockLogger
{
    private Connection $db;
    private string $projectDir;

    public function __construct(Connection $db, string $projectDir)
    {
        $this->db = $db;
        $this->projectDir = $projectDir;
    }

    public function logSqlLocks(string $comment = ''): void
    {
        $fileName = sprintf(
            '%s/var/log/pickware_debug_sql_locks.%s.log',
            $this->projectDir,
            (new DateTime())->format('Y-m-d-H-i-s-u'),
        );

        $mysqlConnectionId = $this->db->fetchOne('SELECT CONNECTION_ID() as connid');

        $fileContent = sprintf(
            "Comment:\t\t\"%s\"\nPHP Process ID:\t\t%s\nMySQL Connection ID:\t%s\n\n",
            $comment,
            getmypid(),
            $mysqlConnectionId,
        );

        $locks = $this->db->fetchAllAssociative('
            SELECT
                performance_schema.threads.PROCESSLIST_ID,
                performance_schema.metadata_locks.*
            FROM
                performance_schema.threads,
                performance_schema.metadata_locks
            WHERE
                performance_schema.threads.THREAD_ID = performance_schema.metadata_locks.OWNER_THREAD_ID;');

        if (count($locks) === 0) {
            $fileContent .= 'No locks.';
        } else {
            $header = implode(', ', array_keys($locks[0]));
            $line = str_repeat('-', mb_strlen($header));
            $fileContent .= $line . "\n" . $header . "\n" . $line . "\n";

            foreach ($locks as $lock) {
                $fileContent .= implode(",\t", array_values($lock)) . "\n";
            }

            $fileContent .= $line;
        }

        file_put_contents($fileName, $fileContent);
    }
}
