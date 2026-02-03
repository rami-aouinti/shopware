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

class Migration1766052595AddOutputAnalysisIndices extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1766052595;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE pickware_wms_pick_event
              ADD INDEX `pickware_wms_pick_event.idx.pe_weekday_created_qty`
                (`pick_created_at_weekday`, `pick_created_at`, `picked_quantity`),

              ADD INDEX `pickware_wms_pick_event.idx.pe_hour_created_user`
                (`pick_created_at_hour`, `pick_created_at`, `user_reference_id`),

              ADD INDEX `pickware_wms_pick_event.idx.pe_weekday_created_user`
                (`pick_created_at_weekday`, `pick_created_at`, `user_reference_id`);
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_wms_delivery_lifecycle_event`
              ADD INDEX `pw_wms_delivery_lifecycle_event.idx.evt_created_wd_created`
                (`event_created_at_weekday`, `event_created_at`),

              ADD INDEX `pw_wms_delivery_lifecycle_event.idx.evt_tec_hr_created`
                (`event_technical_name`, `event_created_at_hour`, `event_created_at`),

              ADD INDEX `pw_wms_delivery_lifecycle_event.idx.evt_tec_wd_created`
                (`event_technical_name`, `event_created_at_weekday`, `event_created_at`);
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_wms_picking_process_lifecycle_event`
              ADD INDEX `pw_wms_picking_process_lifecycle_event.idx.evt_created_wd_crtd`
                (`event_created_at_weekday`, `event_created_at`),

              ADD INDEX `pw_wms_picking_process_lifecycle_event.idx.evt_tec_hr_created`
                (`event_technical_name`, `event_created_at_hour`, `event_created_at`),

              ADD INDEX `pw_wms_picking_process_lifecycle_event.idx.evt_tec_wd_created`
                (`event_technical_name`, `event_created_at_weekday`, `event_created_at`);
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
