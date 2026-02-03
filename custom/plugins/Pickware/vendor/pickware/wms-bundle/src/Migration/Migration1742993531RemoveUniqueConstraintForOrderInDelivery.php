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

class Migration1742993531RemoveUniqueConstraintForOrderInDelivery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1742993531;
    }

    public function update(Connection $connection): void
    {
        // We need to drop the foreign key on `order` first and re-add it later because it uses the unique index
        // which we want to remove
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_wms_delivery`
                    DROP FOREIGN KEY `pickware_wms_delivery.fk.order`;
                ALTER TABLE `pickware_wms_delivery`
                    DROP INDEX `pickware_wms_delivery.uidx.order`;
                ALTER TABLE `pickware_wms_delivery`
                    ADD CONSTRAINT `pickware_wms_delivery.fk.order`
                    FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE;
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
