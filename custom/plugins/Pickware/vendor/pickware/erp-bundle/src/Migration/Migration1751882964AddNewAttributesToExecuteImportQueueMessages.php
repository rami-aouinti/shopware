<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1751882964AddNewAttributesToExecuteImportQueueMessages extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1751882964;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
            UPDATE messenger_messages
            JOIN
                pickware_erp_import_export
                    ON UNHEX(JSON_UNQUOTE(JSON_EXTRACT(messenger_messages.body, '$.importExportId'))) = pickware_erp_import_export.id
            SET
                messenger_messages.body = JSON_SET(
                    messenger_messages.body,
                    -- The read specification is now stored in the message instead of the import export entity
                    '$.nextRowNumberToRead', JSON_EXTRACT(pickware_erp_import_export.state_data, '$.nextRowNumberToRead'),
                    -- All currently running imports are still sequential instead of parallel, so they need to continue
                    -- their recursive message chain
                    '$.spawnNextMessage', 1
                )
            WHERE
                -- Filter for execute-import messages
                -- Note: we use % instead of writing out backslashes because, for unknown reason, the where clause
                -- never succeeds that way, with any number of backslashes.
                JSON_UNQUOTE(JSON_EXTRACT(messenger_messages.headers, '$.type')) LIKE 'Pickware%PickwareErpStarter%ImportExport%ImportExportSchedulerMessage'
                AND JSON_UNQUOTE(JSON_EXTRACT(messenger_messages.body, '$.state')) = 'execute-import'
            ;
            SQL);
    }

    public function updateDestructive(Connection $connection): void {}
}
