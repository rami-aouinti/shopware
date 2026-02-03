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

class Migration1669191282RemovePickingProcessTrackingCodeUniqueConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1669191282;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process_tracking_code`
            DROP INDEX `pw_wms_picking_process_tracking_code.uidx.code`',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
