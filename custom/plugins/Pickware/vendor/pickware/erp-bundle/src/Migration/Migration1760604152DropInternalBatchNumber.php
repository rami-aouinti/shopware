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

class Migration1760604152DropInternalBatchNumber extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1760604152;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                UPDATE `pickware_erp_batch`
                SET `external_number` = `internal_number` WHERE `external_number` IS NULL;
                SQL,
        );

        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_erp_batch`
                DROP COLUMN `internal_number`,
                CHANGE COLUMN `external_number` `number` VARCHAR(255) NOT NULL,
                DROP INDEX `pickware_erp_batch.uidx.external_number`,
                ADD UNIQUE INDEX `pickware_erp_batch.uidx.number` (`number`, `product_id`, `product_version_id`);
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
