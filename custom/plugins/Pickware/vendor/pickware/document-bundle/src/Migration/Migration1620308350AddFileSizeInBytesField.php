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

class Migration1620308350AddFileSizeInBytesField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1620308350;
    }

    public function update(Connection $connection): void
    {
        // Remove default value of -1
        // The actual file size will be set in the class DocumentFileSizeMigrator
        $connection->executeStatement(
            'ALTER TABLE `pickware_document`
            CHANGE `file_size_in_bytes`
                `file_size_in_bytes` BIGINT(20) NOT NULL',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
