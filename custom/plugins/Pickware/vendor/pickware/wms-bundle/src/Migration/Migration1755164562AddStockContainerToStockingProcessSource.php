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

class Migration1755164562AddStockContainerToStockingProcessSource extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1755164562;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_stocking_process_source`
                ADD COLUMN `stock_container_id` BINARY(16) NULL AFTER `goods_receipt_id`,
                ADD CONSTRAINT `pw_wms_stocking_process_source.fk.stock_container`
                    FOREIGN KEY (`stock_container_id`)
                    REFERENCES `pickware_erp_stock_container` (`id`)
                    ON UPDATE CASCADE
                    ON DELETE RESTRICT
            ',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
