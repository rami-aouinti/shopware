<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1733673600SanitizeDocumentFileNames extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1733673600;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
                UPDATE `pickware_document`
                SET `file_name` = REPLACE(REPLACE(`file_name`, '/', '_'), '\\', '_')
                WHERE `file_name` LIKE '%/%' OR `file_name` LIKE '%\\%';
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
