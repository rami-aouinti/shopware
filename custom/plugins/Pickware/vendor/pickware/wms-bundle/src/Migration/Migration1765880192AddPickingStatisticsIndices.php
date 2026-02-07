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

class Migration1765880192AddPickingStatisticsIndices extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1765880192;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE INDEX `pickware_wms_pick_event_user_role.idx.pick_user_role`
             ON `pickware_wms_pick_event_user_role` (`pick_id`, `user_role_reference_id`)',
        );

        $connection->executeStatement(
            'CREATE INDEX `pickware_wms_delivery_lifecycle_evt_user_role.idx.evt_role`
             ON `pickware_wms_delivery_lifecycle_event_user_role` (`delivery_lifecycle_event_id`, `user_role_reference_id`)',
        );

        $connection->executeStatement(
            'CREATE INDEX `pickware_wms_pick_proc_lifecycle_evt_user_role.idx.evt_role`
             ON `pickware_wms_picking_process_lifecycle_event_user_role` (`picking_process_lifecycle_event_id`, `user_role_reference_id`)',
        );

        $connection->executeStatement(
            'CREATE INDEX `pickware_wms_pick_event.idx.picking_mode_created`
             ON `pickware_wms_pick_event` (`picking_mode`, `pick_created_at`)',
        );

        $connection->executeStatement(
            'CREATE INDEX `pickware_wms_delivery_lifecycle_event.idx.complete_multi_filter`
             ON `pickware_wms_delivery_lifecycle_event` (`event_technical_name`, `event_created_at`, `warehouse_reference_id`, `picking_profile_reference_id`, `picking_mode`, `user_reference_id`)',
        );

        $connection->executeStatement(
            'CREATE INDEX `pickware_wms_delivery_lifecycle_event.idx.user_evt_created_at`
             ON `pickware_wms_delivery_lifecycle_event` (`user_reference_id`, `event_created_at`)',
        );

        $connection->executeStatement(
            'CREATE INDEX `pickware_wms_pick_proc_lifecycle_evt.idx.user_evt_created`
             ON `pickware_wms_picking_process_lifecycle_event` (`user_reference_id`, `event_created_at`)',
        );

        $connection->executeStatement(
            'CREATE INDEX `pickware_wms_pick_event.idx.multi_filter_stats`
             ON `pickware_wms_pick_event` (`pick_created_at`, `warehouse_reference_id`, `picking_profile_reference_id`, `picking_mode`, `user_reference_id`, `pick_created_at_day`, `pick_created_at_hour`)',
        );

        $connection->executeStatement(
            'CREATE INDEX `pickware_wms_delivery_lifecycle_evt.idx.perf_analysis_cvr`
             ON `pickware_wms_delivery_lifecycle_event` (`event_technical_name`, `event_created_at`, `user_reference_id`, `event_created_at_day`, `event_created_at_hour`)',
        );

        $connection->executeStatement(
            'CREATE INDEX `pickware_wms_picking_process_lifecycle_event.idx.multi_filter`
             ON `pickware_wms_picking_process_lifecycle_event` (`event_technical_name`, `event_created_at`, `warehouse_reference_id`, `picking_profile_reference_id`, `picking_mode`, `user_reference_id`)',
        );

        $connection->executeStatement(
            'CREATE INDEX `pickware_wms_pick_event.idx.user_max_created`
             ON `pickware_wms_pick_event` (`user_reference_id`, `pick_created_at` DESC)',
        );

        $connection->executeStatement(
            'CREATE INDEX `pickware_wms_delivery_lifecycle_evt.idx.pick_mode_evt_name`
             ON `pickware_wms_delivery_lifecycle_event` (`picking_mode`, `event_technical_name`, `event_created_at`)',
        );

        $connection->executeStatement(
            'CREATE INDEX `pickware_wms_pick_event.idx.perf_analysis_covering`
             ON `pickware_wms_pick_event` (`pick_created_at`, `user_reference_id`, `pick_created_at_day`, `pick_created_at_hour`, `picked_quantity`)',
        );

        $connection->executeStatement(
            'CREATE INDEX `pickware_wms_picking_process_lifecycle_event.idx.perf_covering`
             ON `pickware_wms_picking_process_lifecycle_event` (`event_technical_name`, `event_created_at`, `user_reference_id`, `event_created_at_day`, `event_created_at_hour`)',
        );

        $connection->executeStatement(
            'CREATE INDEX `pickware_wms_delivery_lifecycle_evt.idx.sales_channel_anl`
             ON `pickware_wms_delivery_lifecycle_event` (`sales_channel_reference_id`, `event_created_at`, `event_technical_name`)',
        );

        $connection->executeStatement(
            'CREATE INDEX `pickware_wms_picking_process_lifecycle_event.idx.picking_mode`
             ON `pickware_wms_picking_process_lifecycle_event` (`picking_mode`, `event_created_at`)',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
