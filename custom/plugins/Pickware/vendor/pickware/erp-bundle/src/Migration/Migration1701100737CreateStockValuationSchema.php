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

class Migration1701100737CreateStockValuationSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1701100737;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_stock_valuation_report` (
                `id` BINARY(16) NOT NULL,
                `reporting_day` DATE NOT NULL,
                `reporting_day_time_zone` VARCHAR(255) NOT NULL,
                `until_date` DATETIME(3) NULL,
                `generated` TINYINT(1) NOT NULL,
                `comment` TEXT,
                `preview` TINYINT(1) NOT NULL,
                `method` VARCHAR(255) NOT NULL,
                `warehouse_id` BINARY(16) NULL,
                `warehouse_snapshot` JSON,
                `generation_step` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_erp_stock_valuation_report.fk.warehouse`
                    FOREIGN KEY (`warehouse_id`)
                    REFERENCES `pickware_erp_warehouse` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_stock_valuation_report_row` (
                `id` BINARY(16) NOT NULL,
                `report_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NULL,
                `product_version_id` BINARY(16) NULL,
                `product_snapshot` JSON,
                `stock` INT(11) NOT NULL,
                `valuation_net` DECIMAL(10,2) NULL,
                `valuation_gross` DECIMAL(10,2) NULL,
                `tax_rate` FLOAT NOT NULL,
                `average_purchase_price_net` DECIMAL(10,2) NOT NULL,
                `surplus_stock` INT(11) DEFAULT 0,
                `surplus_purchase_price_net` DECIMAL(10,2) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `pickware_erp_stock_valuation_report_row.uidx.report_product` (`report_id`, `product_id`, `product_version_id`),
                CONSTRAINT `pickware_erp_stock_valuation_report_row.fk.product`
                    FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stock_valuation_report_row.fk.report`
                    FOREIGN KEY (`report_id`)
                    REFERENCES `pickware_erp_stock_valuation_report` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_stock_valuation_report_purchase` (
                `id` BINARY(16) NOT NULL,
                `report_row_id` BINARY(16) NOT NULL,
                `date` DATETIME(3) NOT NULL,
                `purchase_price_net` DECIMAL(10,2) NOT NULL,
                `quantity` INT(11) NOT NULL,
                `quantity_used_for_valuation` INT NOT NULL DEFAULT 0,
                `type` VARCHAR(255) NOT NULL,
                `goods_receipt_line_item_id` BINARY(16) NULL,
                `carry_over_report_row_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_erp_stock_valuation_report_purchase.fk.report_row`
                    FOREIGN KEY (`report_row_id`)
                    REFERENCES `pickware_erp_stock_valuation_report_row` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `pckwr_erp_stock_vltn_report_purchase.fk.goods_receipt_line_item`
                    FOREIGN KEY (`goods_receipt_line_item_id`)
                    REFERENCES `pickware_erp_goods_receipt_line_item` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_stock_valuation_report_row.fk.carry_over_report_row`
                    FOREIGN KEY (`carry_over_report_row_id`)
                    REFERENCES `pickware_erp_stock_valuation_report_row` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
