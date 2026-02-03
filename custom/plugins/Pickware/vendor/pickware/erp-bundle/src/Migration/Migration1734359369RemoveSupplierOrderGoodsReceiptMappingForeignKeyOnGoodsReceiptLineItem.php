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

class Migration1734359369RemoveSupplierOrderGoodsReceiptMappingForeignKeyOnGoodsReceiptLineItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1734359369;
    }

    // phpcs:disable ShopwarePlugins.Migration.ForeignKeyIndexPair.MissingDropIndex
    // Foreign key was later added in Migration1699382618AddGoodsReceiptsForReturnOrders anew.
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_goods_receipt_line_item`
            DROP FOREIGN KEY `pickware_erp_goods_receipt_item.fk.supplier_order_goods_receipt`
        ');
    }
}
