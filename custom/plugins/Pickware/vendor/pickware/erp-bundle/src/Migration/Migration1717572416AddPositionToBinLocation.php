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

class Migration1717572416AddPositionToBinLocation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1717572416;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_bin_location`
            ADD COLUMN `position` INT(11) UNSIGNED DEFAULT NULL CHECK (position > 0) AFTER `warehouse_id`;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
