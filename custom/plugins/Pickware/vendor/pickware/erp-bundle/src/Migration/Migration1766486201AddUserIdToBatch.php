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

class Migration1766486201AddUserIdToBatch extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1766486201;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_erp_batch`
                    ADD COLUMN `user_id` BINARY(16) NULL AFTER `physical_stock`,
                    ADD COLUMN `user_snapshot` JSON NULL AFTER `user_id`,
                    ADD CONSTRAINT `pickware_erp_batch.fk.user`
                        FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
                        ON DELETE SET NULL ON UPDATE CASCADE
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
