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

class Migration1729775535AddProductSupplierConfigurationDevEntity extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1729775535;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE `pickware_erp_product_supplier_configuration_dev` (
                    `id` BINARY(16) NOT NULL,
                    `product_id` BINARY(16) NOT NULL,
                    `product_version_id` BINARY(16) NOT NULL,
                    `supplier_id` BINARY(16) NOT NULL,
                    `supplier_product_number` VARCHAR(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `min_purchase` INT NOT NULL DEFAULT '1',
                    `purchase_steps` INT NOT NULL DEFAULT '1',
                    `purchase_prices` JSON NOT NULL,
                    `supplier_is_default` BOOLEAN DEFAULT FALSE NOT NULL,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `pickware_erp_product_supplier_conf_dev.uidx.product_supplier` (`product_id`, `product_version_id`, `supplier_id`),
                    KEY `pickware_erp_product_supplier_conf_dev.fk.supplier` (`supplier_id`),
                    CONSTRAINT `pickware_erp_product_supplier_conf_dev.fk.product`
                        FOREIGN KEY (`product_id`, `product_version_id`)
                        REFERENCES `product` (`id`, `version_id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE,
                    CONSTRAINT `pickware_erp_product_supplier_conf_dev.fk.supplier`
                        FOREIGN KEY (`supplier_id`)
                        REFERENCES `pickware_erp_supplier` (`id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL
        );
    }
}
