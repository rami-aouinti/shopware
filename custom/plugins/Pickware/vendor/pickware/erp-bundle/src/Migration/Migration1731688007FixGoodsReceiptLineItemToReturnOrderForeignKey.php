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

class Migration1731688007FixGoodsReceiptLineItemToReturnOrderForeignKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1731688007;
    }

    // phpcs:disable ShopwarePlugins.Migration.ForeignKeyIndexPair.MissingDropIndex
    // Is already re-created in this migration and we do not touch it retrospectively.
    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
            ALTER TABLE `pickware_erp_goods_receipt_line_item`
            DROP FOREIGN KEY `pickware_erp_goods_receipt.fk.return_order`;
            SQL
        );
        $connection->executeStatement(<<<SQL
            ALTER TABLE `pickware_erp_goods_receipt_line_item`
            ADD CONSTRAINT `pickware_erp_goods_receipt.fk.return_order`
                FOREIGN KEY (`return_order_id`, `return_order_version_id`)
                REFERENCES `pickware_erp_return_order` (`id`, `version_id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE;
            SQL
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
