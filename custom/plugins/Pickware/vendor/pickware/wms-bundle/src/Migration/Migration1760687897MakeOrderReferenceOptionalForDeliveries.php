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

class Migration1760687897MakeOrderReferenceOptionalForDeliveries extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1760687897;
    }

    public function update(Connection $connection): void
    {
        // Change order reference to be optional (ON DELETE SET NULL)
        $connection->executeStatement('
            ALTER TABLE `pickware_wms_delivery`
                DROP FOREIGN KEY `pickware_wms_delivery.fk.order`;
            ALTER TABLE `pickware_wms_delivery`
                DROP INDEX `pickware_wms_delivery.fk.order`;
            ALTER TABLE `pickware_wms_delivery`
                MODIFY `order_id` BINARY(16) DEFAULT NULL,
                MODIFY `order_version_id` BINARY(16) DEFAULT NULL,
                ADD CONSTRAINT `pickware_wms_delivery.fk.order`
                    FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
