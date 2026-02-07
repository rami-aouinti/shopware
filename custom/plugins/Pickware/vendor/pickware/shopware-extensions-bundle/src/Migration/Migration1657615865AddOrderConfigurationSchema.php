<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1657615865AddOrderConfigurationSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1657615865;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE `pickware_shopware_extensions_order_configuration` (
                `id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `order_version_id` BINARY(16) NOT NULL,
                `primary_order_transaction_id` BINARY(16) NULL,
                `primary_order_transaction_version_id` BINARY(16) NULL,
                `primary_order_delivery_id` BINARY(16) NULL,
                `primary_order_delivery_version_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_sw_ext_o_configuration.uidx.order` (`order_id`),
                CONSTRAINT `pickware_sw_ext_o_configuration.fk.order`
                    FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_sw_ext_o_configuration.fk.transaction`
                    FOREIGN KEY (`primary_order_transaction_id`, `primary_order_transaction_version_id`)
                    REFERENCES `order_transaction` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `pickware_sw_ext_o_configuration.fk.delivery`
                    FOREIGN KEY (`primary_order_delivery_id`, `primary_order_delivery_version_id`)
                    REFERENCES `order_delivery` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
