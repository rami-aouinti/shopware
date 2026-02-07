<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1761728271AddOrderTypeToUsageReportOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1761728271;
    }

    public function update(Connection $connection): void
    {
        // Add the new order_type column
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_usage_report_order`
                ADD COLUMN `order_type` VARCHAR(255) NULL AFTER `ordered_at`;
                SQL,
        );

        // Migrate existing data: is_pos_order = 0 -> 'regular', is_pos_order = 1 -> 'pickware_pos'
        $connection->executeStatement(
            <<<SQL
                UPDATE `pickware_usage_report_order`
                SET `order_type` = IF(`is_pos_order` = 1, 'pickware_pos', 'regular');
                SQL,
        );

        // Make order_type NOT NULL now that all rows have values
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_usage_report_order`
                MODIFY COLUMN `order_type` VARCHAR(255) NOT NULL;
                SQL,
        );

        // Drop the old is_pos_order column
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_usage_report_order`
                DROP COLUMN `is_pos_order`;
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
