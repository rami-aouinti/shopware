<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1672303791AddIsPosOrderCustomField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1672303791;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE  `order`
            LEFT JOIN `sales_channel` ON `order`.`sales_channel_id` = `sales_channel`.`id`
            LEFT JOIN `pickware_pos_order_branch_store_mapping` ON `order`.`id` = `pickware_pos_order_branch_store_mapping`.`order_id`
            SET `order`.`custom_fields` = JSON_MERGE_PATCH(
                IFNULL(`order`.`custom_fields`, json_object()),
                json_object("isPosOrder", true)
            )
            WHERE
                `sales_channel`.`type_id` = UNHEX(:posSalesChannelTypeId)
                OR `pickware_pos_order_branch_store_mapping`.`branch_store_id` IS NOT NULL
            ',
            ['posSalesChannelTypeId' => 'd18beabacf894e14b507767f4358eeb0'],
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
