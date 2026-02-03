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

class Migration1746519262AddShippingProcessSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1746519262;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_wms_shipping_process` (
                `id` BINARY(16) NOT NULL,
                `number` VARCHAR(255) NOT NULL,
                `state_id` BINARY(16) NOT NULL,
                `warehouse_id` BINARY(16) NOT NULL,
                `user_id` BINARY(16) NULL,
                `device_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_wms_shipping_process.uidx.number` (`number`),
                CONSTRAINT `pickware_wms_shipping_process.fk.state`
                    FOREIGN KEY (`state_id`)
                    REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_wms_shipping_process.fk.warehouse`
                    FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_wms_shipping_process.fk.user`
                    FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `pickware_wms_shipping_process.fk.device`
                    FOREIGN KEY (`device_id`)
                    REFERENCES `pickware_wms_device` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process`
                ADD COLUMN `shipping_process_id` BINARY(16) NULL AFTER `pre_collecting_stock_container_id`,
                ADD CONSTRAINT `pickware_wms_picking_process.fk.shipping_process`
                    FOREIGN KEY (`shipping_process_id`)
                    REFERENCES `pickware_wms_shipping_process` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
