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

class Migration1752763260AddPositionFieldToReturnOrderLineItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1752763260;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_return_order_line_item`
            ADD COLUMN `position` INT(11) DEFAULT 1 NOT NULL AFTER `quantity`;',
        );

        $connection->executeStatement(
            'UPDATE `pickware_erp_return_order_line_item` AS returnLineItem
            INNER JOIN (
                SELECT
                    `pickware_erp_return_order_line_item`.id,
                    `pickware_erp_return_order_line_item`.version_id,
                    ROW_NUMBER() OVER (
                        PARTITION BY return_order_id, return_order_version_id
                        ORDER BY product.product_number DESC
                    ) AS new_position
                FROM `pickware_erp_return_order_line_item`
                LEFT JOIN product AS product
                    ON `pickware_erp_return_order_line_item`.product_id = product.id
                    AND `pickware_erp_return_order_line_item`.product_version_id = product.version_id
            ) AS sortedReturnLineItem
                ON returnLineItem.id = sortedReturnLineItem.id
                AND returnLineItem.version_id = sortedReturnLineItem.version_id
            SET returnLineItem.position = sortedReturnLineItem.new_position;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
