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

class Migration1703772927CreateStockValuationReportTemporaryTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1703772927;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_stock_valuation_temp_stock` (
                `id` BINARY(16) NOT NULL,
                `report_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `stock` INT(11) NOT NULL,
                `average_purchase_price_net` DECIMAL(10,2) NULL,
                `valuation_net` DECIMAL(10,2) NULL,
                -- If there is more stock than the sum of purchased stock, this is the surplus stock, that is not
                -- included in purchases
                `surplus_stock` INT(11) NULL DEFAULT 0,
                -- The surplus stock will be valued by a "guessed" purchase price. This purchase price is saved for
                -- documentation.
                `surplus_purchase_price_net` DECIMAL(10,2) NULL,
                PRIMARY KEY (`id`),
                INDEX `pickware_erp_stock_valuation_temp_stock.idx.report` (`report_id`),
                INDEX `pickware_erp_stock_valuation_temp_stock.idx.product` (`product_id`, `product_version_id`),
                INDEX `pckwr_erp_stock_valuation_temp_stock.idx.stock_report_product` (`stock`, `report_id`, `product_id`, `product_version_id`),
                CONSTRAINT `pickware_erp_stock_valuation_temp_stock.fk.report`
                    FOREIGN KEY (`report_id`)
                    REFERENCES `pickware_erp_stock_valuation_report` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_stock_valuation_temp_purchase` (
                `id` BINARY(16) NOT NULL,
                `report_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `quantity` INT(11) NOT NULL,
                `quantity_used_for_valuation` INT(11) NULL DEFAULT 0,
                `purchase_price_net` DECIMAL(10,2) NOT NULL,
                `date` DATETIME NOT NULL,
                `type` VARCHAR(255) NOT NULL,
                `average_purchase_price_net` DECIMAL(10,2) NULL,
                `goods_receipt_line_item_id` BINARY(16) NULL,
                `carry_over_report_row_id` BINARY(16) NULL,
                PRIMARY KEY (`id`),
                INDEX `pickware_erp_stock_valuation_temp_purchase.idx.report` (`report_id`),
                INDEX `pickware_erp_stock_valuation_temp_purchase.idx.product` (`product_id`, `product_version_id`),
                INDEX `pckwr_erp_stock_vltn_temp_prchs.idx.product_report_date_price` (`product_id`, `product_version_id`, `report_id`, `date`, `purchase_price_net`),
                CONSTRAINT `pickware_erp_stock_valuation_temp_purchase.fk.report`
                    FOREIGN KEY (`report_id`)
                    REFERENCES `pickware_erp_stock_valuation_report` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
