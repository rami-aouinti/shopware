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

class Migration1637931476CreateReturnOrderSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1637931476;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_return_order` (
                `id` BINARY(16) NOT NULL,
                `version_id` BINARY(16) NOT NULL,
                `price` JSON NOT NULL,
                `amount_total` DOUBLE GENERATED ALWAYS AS (json_unquote(json_extract(`price`,"$.totalPrice"))) VIRTUAL,
                `amount_net` DOUBLE GENERATED ALWAYS AS (json_unquote(json_extract(`price`,"$.netPrice"))) VIRTUAL,
                `position_price` DOUBLE GENERATED ALWAYS AS (json_unquote(json_extract(`price`,"$.positionPrice"))) VIRTUAL,
                `tax_status` VARCHAR(255) GENERATED ALWAYS AS (json_unquote(json_extract(`price`,"$.taxStatus"))) VIRTUAL,
                `internal_comment` TEXT DEFAULT NULL,
                `number` VARCHAR(255) NOT NULL,
                `state_id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `order_version_id` BINARY(16) NOT NULL,
                `warehouse_id` BINARY(16) DEFAULT NULL,
                `user_id` BINARY(16) DEFAULT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) DEFAULT NULL,
                PRIMARY KEY (`id`, `version_id`),
                    CONSTRAINT `pickware_erp_return_order.fk.warehouse`
                        FOREIGN KEY (`warehouse_id`)
                        REFERENCES `pickware_erp_warehouse` (`id`)
                        ON DELETE SET NULL
                        ON UPDATE CASCADE,
                    CONSTRAINT `pickware_erp_return_order.fk.order`
                        FOREIGN KEY (`order_id`,`order_version_id`)
                        REFERENCES `order` (`id`, `version_id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE,
                    CONSTRAINT `pickware_erp_return_order.fk.user`
                        FOREIGN KEY (`user_id`)
                        REFERENCES `user` (`id`)
                        ON DELETE SET NULL
                        ON UPDATE CASCADE,
                    CONSTRAINT `pickware_erp_return_order.fk.state`
                        FOREIGN KEY (`state_id`)
                        REFERENCES `state_machine_state` (`id`)
                        ON DELETE RESTRICT
                        ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_return_order_line_item` (
                `id` BINARY(16) NOT NULL,
                `version_id` BINARY(16) NOT NULL,
                `type` ENUM("product", "promotion", "custom", "credit") NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `quantity` INT(11) NOT NULL CHECK (quantity > 0),
                `price_definition` JSON,
                `price` JSON NOT NULL,
                `unit_price` DOUBLE GENERATED ALWAYS AS (json_unquote(json_extract(`price`,"$.unitPrice"))) VIRTUAL,
                `total_price` DOUBLE GENERATED ALWAYS AS (json_unquote(json_extract(`price`,"$.totalPrice"))) VIRTUAL,
                `product_id` BINARY(16) DEFAULT NULL,
                `product_version_id` BINARY(16) DEFAULT NULL,
                `product_number` VARCHAR(64) NULL,
                `return_order_id` BINARY(16) NOT NULL,
                `return_order_version_id` BINARY(16) NOT NULL,
                `order_line_item_id` BINARY(16) DEFAULT NULL,
                `order_line_item_version_id` BINARY(16) DEFAULT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) DEFAULT NULL,
                PRIMARY KEY (`id`, `version_id`),
                CONSTRAINT `pickware_erp_return_order_line_item.fk.product`
                    FOREIGN KEY (`product_id`,`product_version_id`)
                    REFERENCES `product` (`id`, `version_id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_return_order_line_item.fk.order_line_item`
                    FOREIGN KEY (`order_line_item_id`,`order_line_item_version_id`)
                    REFERENCES `order_line_item` (`id`, `version_id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_return_order_line_item.fk.return_order`
                    FOREIGN KEY (`return_order_id`, `return_order_version_id`)
                    REFERENCES `pickware_erp_return_order` (`id`, `version_id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_return_order_refund` (
                `id` BINARY(16) NOT NULL,
                `version_id` BINARY(16) NOT NULL,
                `money_value` JSON NOT NULL,
                `currency_iso_code` CHAR(3) GENERATED ALWAYS AS (json_unquote(json_extract(`money_value`,"$.currency.isoCode"))) VIRTUAL,
                `amount` DOUBLE GENERATED ALWAYS AS (json_unquote(json_extract(`money_value`,"$.value"))) VIRTUAL,
                `state_id` BINARY(16) NOT NULL,
                `return_order_id` BINARY(16) NOT NULL,
                `return_order_version_id` BINARY(16) NOT NULL,
                `payment_method_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) DEFAULT NULL,
                PRIMARY KEY (`id`, `version_id`),
                UNIQUE KEY `pickware_erp_return_order_refund.uidx.return_order` (`return_order_id`, `return_order_version_id`),
                CONSTRAINT `pickware_erp_return_order_refund.fk.return_order`
                    FOREIGN KEY (`return_order_id`, `return_order_version_id`)
                    REFERENCES `pickware_erp_return_order` (`id`, `version_id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_return_order_refund.fk.state`
                    FOREIGN KEY (`state_id`)
                    REFERENCES state_machine_state (`id`)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_return_order_refund.fk.payment_method`
                    FOREIGN KEY (`payment_method_id`)
                    REFERENCES `payment_method` (`id`)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
