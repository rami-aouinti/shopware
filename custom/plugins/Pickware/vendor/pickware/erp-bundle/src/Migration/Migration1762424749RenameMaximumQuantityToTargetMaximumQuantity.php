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

class Migration1762424749RenameMaximumQuantityToTargetMaximumQuantity extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1762424749;
    }

    public function update(Connection $db): void
    {
        $db->executeStatement(
            'ALTER TABLE `pickware_erp_pickware_product`
                CHANGE COLUMN `maximum_quantity` `target_maximum_quantity` INT(11) NULL;',
        );

        $db->executeStatement(
            'ALTER TABLE `pickware_erp_product_warehouse_configuration`
                CHANGE COLUMN `maximum_quantity` `target_maximum_quantity` INT(11) NULL;',
        );

        $db->executeStatement(
            'ALTER TABLE `pickware_erp_product_stock_location_configuration`
                CHANGE COLUMN `maximum_quantity` `target_maximum_quantity` INT(11) NULL;',
        );
    }

    public function updateDestructive(Connection $db): void {}
}
