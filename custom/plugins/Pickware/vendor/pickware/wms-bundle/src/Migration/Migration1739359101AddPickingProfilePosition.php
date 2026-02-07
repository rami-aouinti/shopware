<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1739359101AddPickingProfilePosition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1739359101;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_profile`
            ADD COLUMN `position` INT(11) NULL',
        );

        // The WMS app used to sort picking profiles by their creation date. Hence assign the positions to existing
        // picking profiles while maintaining their order when sorting by position.
        $connection->executeStatement(
            'SET @position = 0;
            UPDATE `pickware_wms_picking_profile`
            SET `position` = (@position := @position + 1)
            ORDER BY `created_at` ASC;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_profile`
            CHANGE COLUMN `position` `position` INT(11) NOT NULL',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
