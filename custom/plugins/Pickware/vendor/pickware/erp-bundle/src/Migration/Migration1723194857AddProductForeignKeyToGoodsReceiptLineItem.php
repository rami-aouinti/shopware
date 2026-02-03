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

class Migration1723194857AddProductForeignKeyToGoodsReceiptLineItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1723194857;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `pickware_erp_goods_receipt_line_item`
                LEFT JOIN `product` ON `pickware_erp_goods_receipt_line_item`.product_id = `product`.id
                SET `pickware_erp_goods_receipt_line_item`.`product_id` = NULL
                WHERE `product`.id IS NULL;',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_goods_receipt_line_item`
                ADD CONSTRAINT `pickware_erp_goods_receipt_line_item.fk.product`
                FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
