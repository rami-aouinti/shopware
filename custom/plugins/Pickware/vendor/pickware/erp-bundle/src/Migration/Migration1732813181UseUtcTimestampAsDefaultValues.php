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

class Migration1732813181UseUtcTimestampAsDefaultValues extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1732813181;
    }

    public function update(Connection $connection): void
    {
        $tables = [
            'pickware_erp_goods_receipt',
            'pickware_erp_goods_receipt_document_mapping',
            'pickware_erp_goods_receipt_line_item',
            'pickware_erp_import_export_profile',
            'pickware_erp_product_sales_update_queue',
            'pickware_erp_stocktaking_stocktake',
            'pickware_erp_stocktaking_stocktake_counting_process',
            'pickware_erp_stocktaking_stocktake_counting_process_item',
            'pickware_erp_stocktaking_stocktake_product_summary',
            'pickware_erp_stocktaking_stocktake_snapshot_item',
        ];

        foreach ($tables as $table) {
            $connection->executeStatement(<<<SQL
                ALTER TABLE `{$table}`
                    CHANGE `created_at` `created_at` DATETIME(3) NOT NULL DEFAULT (UTC_TIMESTAMP(3));
                SQL);
        }
    }

    public function updateDestructive(Connection $connection): void {}
}
