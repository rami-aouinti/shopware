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

class Migration1689036093UpdateGoodsReceiptSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1689036093;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_goods_receipt`
                ADD COLUMN `currency_id` BINARY(16) NULL AFTER `number`,
                ADD COLUMN `currency_factor` DOUBLE NULL AFTER `currency_id`,
                ADD COLUMN `item_rounding` JSON DEFAULT NULL AFTER `currency_factor`,
                ADD COLUMN `total_rounding` JSON DEFAULT NULL AFTER `item_rounding`,
                ADD COLUMN `price` JSON DEFAULT NULL AFTER `total_rounding`,
                ADD COLUMN `amount_total` DOUBLE GENERATED ALWAYS AS (IF(`price` IS NULL, NULL, JSON_UNQUOTE(JSON_EXTRACT(`price`,"$.totalPrice")))) VIRTUAL AFTER `price`,
                ADD COLUMN `amount_net` DOUBLE GENERATED ALWAYS AS (IF(`price` IS NULL, NULL, JSON_UNQUOTE(JSON_EXTRACT(`price`,"$.netPrice")))) VIRTUAL AFTER `amount_total`,
                ADD COLUMN `position_price` DOUBLE GENERATED ALWAYS AS (IF(`price` IS NULL, NULL, JSON_UNQUOTE(JSON_EXTRACT(`price`,"$.positionPrice")))) VIRTUAL AFTER `amount_net`,
                ADD COLUMN `tax_status` VARCHAR(255) GENERATED ALWAYS AS (IF(`price` IS NULL, NULL, JSON_UNQUOTE(JSON_EXTRACT(`price`,"$.taxStatus")))) VIRTUAL AFTER `position_price`,
                ADD CONSTRAINT `pickware_erp_goods_receipt.fk.currency`
                    FOREIGN KEY (`currency_id`)
                    REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
