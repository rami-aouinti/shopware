<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1679984792RemoveUniqueConstraintOnBranchStoreFiskalyOrganization extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1679984792;
    }

    public function update(Connection $connection): void
    {
        // Drop unique index on branch store fiskaly organization uuid
        $connection->executeStatement(
            'ALTER TABLE `pickware_pos_branch_store`
            DROP INDEX `pickware_pos_branch_store.uidx.fiskaly_organization_uuid`',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
