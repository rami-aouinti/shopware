<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1706806648AddGoodsReceiptToStockingProcessSource extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1706806648;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_stocking_process_source`
            ADD COLUMN `goods_receipt_id` BINARY(16) NULL AFTER `stock_container_id`,
            ADD CONSTRAINT `pw_wms_stocking_process_source.fk.goods_receipt`
                FOREIGN KEY (`goods_receipt_id`)
                REFERENCES `pickware_erp_goods_receipt` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
