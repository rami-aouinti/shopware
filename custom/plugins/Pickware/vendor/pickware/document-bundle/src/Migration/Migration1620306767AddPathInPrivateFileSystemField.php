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

class Migration1620306767AddPathInPrivateFileSystemField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1620306767;
    }

    public function update(Connection $connection): void
    {
        // Set path_in_private_file_system for each document that does not have a path yet
        $connection->executeStatement(
            'UPDATE `pickware_document`
            SET `path_in_private_file_system` = CONCAT("documents/", LOWER(HEX(`id`)))
            WHERE `path_in_private_file_system` IS NULL',
        );
        // Remove nullability of field
        $connection->executeStatement(
            'ALTER TABLE `pickware_document`
            CHANGE `path_in_private_file_system`
                `path_in_private_file_system` VARCHAR(255) NOT NULL;
',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
