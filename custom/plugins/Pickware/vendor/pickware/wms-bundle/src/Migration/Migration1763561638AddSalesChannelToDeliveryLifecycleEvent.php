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

class Migration1763561638AddSalesChannelToDeliveryLifecycleEvent extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763561638;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_delivery_lifecycle_event`
                ADD COLUMN `sales_channel_reference_id` BINARY(16) NOT NULL AFTER `order_snapshot`,
                ADD COLUMN `sales_channel_snapshot` JSON NOT NULL AFTER `sales_channel_reference_id`,
                ADD INDEX `pw_wms_delivery_lifecycle_event.idx.sales_channel` (`sales_channel_reference_id`);',
        );
    }
}
