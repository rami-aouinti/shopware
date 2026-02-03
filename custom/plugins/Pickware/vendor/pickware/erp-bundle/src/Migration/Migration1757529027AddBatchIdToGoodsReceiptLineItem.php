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

class Migration1757529027AddBatchIdToGoodsReceiptLineItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1757529027;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_goods_receipt_line_item`
                ADD COLUMN `batch_id` BINARY(16) NULL AFTER `product_version_id`;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_goods_receipt_line_item`
                ADD CONSTRAINT `pickware_erp_goods_receipt_line_item.fk.batch`
                FOREIGN KEY (`batch_id`, `product_id`, `product_version_id`)
                    REFERENCES `pickware_erp_batch` (`id`, `product_id`, `product_version_id`)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
