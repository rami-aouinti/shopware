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

class Migration1760452426CreateProductStockLocationMappingAndConfigurationSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1760452426;
    }

    public function update(Connection $db): void
    {
        $db->executeStatement(
            'CREATE TABLE `pickware_erp_product_stock_location_mapping` (
                `id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `warehouse_id` BINARY(16) NULL,
                `bin_location_id` BINARY(16) NULL,
                `stock_id` BINARY(16) NULL,
                `stock_location_type` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pw_erp_product_stock_location_mapping.uidx.prod_bin_loc` (
                    `product_id`,
                    `product_version_id`,
                    `bin_location_id`
                ),
                UNIQUE INDEX `pw_erp_product_stock_location_mapping.uidx.prod_wh` (
                    `product_id`,
                    `product_version_id`,
                    `warehouse_id`
                ),
                INDEX `pw_erp_product_stock_location_mapping.idx.stock` (`stock_id`),
                INDEX `pw_erp_product_stock_location_mapping.idx.product` (`product_id`, `product_version_id`),
                INDEX `pw_erp_product_stock_location_mapping.idx.bin_location` (`bin_location_id`),
                INDEX `pw_erp_product_stock_location_mapping.idx.warehouse` (`warehouse_id`),
                CONSTRAINT `pw_erp_product_stock_location_mapping.fk.stock`
                    FOREIGN KEY (`stock_id`)
                    REFERENCES `pickware_erp_stock` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `pw_erp_product_stock_location_mapping.fk.product`
                    FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pw_erp_product_stock_location_mapping.fk.bin_location`
                    FOREIGN KEY (`bin_location_id`)
                    REFERENCES `pickware_erp_bin_location` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pw_erp_product_stock_location_mapping.fk.warehouse`
                    FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $db->executeStatement(
            'CREATE TABLE `pickware_erp_product_stock_location_configuration` (
                `id` BINARY(16) NOT NULL,
                `product_stock_location_mapping_id` BINARY(16) NOT NULL,
                `reorder_point` INT(11) NULL,
                `maximum_quantity` INT(11) NULL,
                `stock_below_reorder_point` INT(11) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pw_erp_product_stock_location_conf.uidx.mapping` (`product_stock_location_mapping_id`),
                CONSTRAINT `pw_erp_product_stock_location_conf.fk.mapping`
                    FOREIGN KEY (`product_stock_location_mapping_id`)
                    REFERENCES `pickware_erp_product_stock_location_mapping` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $db): void {}
}
