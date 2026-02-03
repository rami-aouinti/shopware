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

class Migration1768470268AddIsExcludedFromReorderNotificationMailToPickwareProduct extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1768470268;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_pickware_product`
            ADD COLUMN `is_excluded_from_reorder_notification_mail` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reorder_point`
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
