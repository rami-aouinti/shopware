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

class Migration1638897517AddReturnOrderAsStockLocation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1638897517;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_stock_movement`
                ADD `source_return_order_id` BINARY(16) NULL AFTER `source_order_version_id`,
                ADD `source_return_order_version_id` BINARY(16) NULL AFTER `source_return_order_id`,
                ADD `destination_return_order_id` BINARY(16) NULL AFTER `destination_order_version_id`,
                ADD `destination_return_order_version_id` BINARY(16) NULL AFTER `destination_return_order_id`,
                ADD INDEX `pickware_erp_stock_movement.idx.source_return_order`
                    (`source_return_order_id`, `source_return_order_version_id`),
                ADD INDEX `pickware_erp_stock_movement.idx.destination_return_order`
                    (`destination_return_order_id`, `destination_return_order_version_id`),
                ADD FOREIGN KEY `pickware_erp_stock_movement.fk.source_return_order`
                    (`source_return_order_id`, `source_return_order_version_id`)
                    REFERENCES `pickware_erp_return_order` (`id`, `version_id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                ADD FOREIGN KEY `pickware_erp_stock_movement.fk.destination_return_order`
                    (`destination_return_order_id`, `destination_return_order_version_id`)
                    REFERENCES `pickware_erp_return_order` (`id`, `version_id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE;
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_stock`
                ADD `return_order_id` BINARY(16) NULL AFTER `order_version_id`,
                ADD `return_order_version_id` BINARY(16) NULL AFTER `return_order_id`,
                ADD INDEX `pickware_erp_stock.idx.return_order` (`return_order_id`, `return_order_version_id`),
                ADD UNIQUE `pickware_erp_stock.uidx.product_return_order`
                    (`product_id`, `product_version_id`, `return_order_id`, `return_order_version_id`),
                ADD FOREIGN KEY `pickware_erp_stock_movement.fk.return_order`
                    (`return_order_id`, `return_order_version_id`)
                    REFERENCES `pickware_erp_return_order` (`id`, `version_id`)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
