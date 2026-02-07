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
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * The plugin code wrote entries in table `pickware_erp_stock_movement` where the field
 * `destination_order_delivery_position_id` was set but the corresponding
 * `destination_order_delivery_position_version_id` field was NULL.
 *
 * Unfortunately MySQL does not enforce neither FK-Constraints nor UNIQUE constraints for composite keys when one
 * of the fields is NULL.
 *
 * This migrations fixes the missing version IDs in these tables.
 */
class Migration1592244872FixMissingOrderDeliveryVersionIdInStockTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1592244872;
    }

    public function update(Connection $db): void
    {
        // Add the live version ID to all stock movements that are missing it for the field
        // `destination_order_delivery_position_version_id`
        $db->executeStatement(
            'UPDATE
                `pickware_erp_stock_movement`
            SET
                `destination_order_delivery_position_version_id` = :liveVersionId,
                `updated_at` = UTC_TIMESTAMP(3)
            WHERE
                `destination_order_delivery_position_id` IS NOT NULL
                AND `destination_order_delivery_position_version_id` IS NULL',
            [
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
        );

        // Due to the missing version id that is fixed with the update above, the table `pickware_erp_stock` may contain
        // incomplete entries. Truncate the `pickware_erp_stock` table so it can be re-populated with correct version id
        // values by doing a full indexing run later.
        $db->executeStatement('TRUNCATE TABLE `pickware_erp_stock`');
    }

    public function updateDestructive(Connection $connection): void {}
}
