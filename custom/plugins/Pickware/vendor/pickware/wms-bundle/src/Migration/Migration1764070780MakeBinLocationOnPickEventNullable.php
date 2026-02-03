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

class Migration1764070780MakeBinLocationOnPickEventNullable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1764070780;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_pick_event`
                MODIFY `bin_location_reference_id` BINARY(16) NULL,
                MODIFY `bin_location_snapshot` JSON NULL',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
