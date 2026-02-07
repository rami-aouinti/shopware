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

class Migration1594822086CreateProductWarehouseConfigurationSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1594822086;
    }

    public function update(Connection $db): void
    {
        $db->executeStatement(
            'CREATE TABLE `pickware_erp_product_warehouse_configuration` (
                `id` BINARY(16) NOT NULL,
                `warehouse_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `default_bin_location_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_erp_product_warehouse_conf.uidx.warehouse_product` (`warehouse_id`, `product_id`),
                CONSTRAINT `pickware_erp_product_warehouse_conf.fk.warehouse`
                    FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_product_warehouse_conf.fk.product`
                    FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_product_warehouse_conf.fk.bin_location`
                    FOREIGN KEY (`default_bin_location_id`)
                    REFERENCES `pickware_erp_bin_location` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $db): void {}
}
