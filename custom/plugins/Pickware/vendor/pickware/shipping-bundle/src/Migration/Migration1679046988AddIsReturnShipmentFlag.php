<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1679046988AddIsReturnShipmentFlag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1679046988;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_shipment`
                ADD COLUMN `is_return_shipment` TINYINT(1) NOT NULL DEFAULT 0
                    AFTER `cancelled`;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
