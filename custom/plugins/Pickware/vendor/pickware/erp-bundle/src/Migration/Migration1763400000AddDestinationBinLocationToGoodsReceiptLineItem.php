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

class Migration1763400000AddDestinationBinLocationToGoodsReceiptLineItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763400000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_erp_goods_receipt_line_item`
                ADD COLUMN `destination_assignment_source` VARCHAR(255) NOT NULL DEFAULT 'unset' AFTER `product_snapshot`,
                ADD COLUMN `destination_bin_location_id` BINARY(16) NULL DEFAULT NULL AFTER `destination_assignment_source`,
                ADD CONSTRAINT `pickware_erp_goods_receipt_line_item.fk.destination_bin_location`
                    FOREIGN KEY (`destination_bin_location_id`)
                    REFERENCES `pickware_erp_bin_location` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE;
                SQL
        );
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_erp_goods_receipt_line_item`
                CHANGE COLUMN `destination_assignment_source` `destination_assignment_source` VARCHAR(255) NOT NULL;
                SQL
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
