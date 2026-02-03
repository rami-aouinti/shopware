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
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1607589490RenameLocationTypeOrderDeliveryPositionToOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1607589490;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `pickware_erp_location_type`
            SET `technical_name` = :locationTypeOrder
            WHERE `technical_name` = "order_delivery_position";',
            [
                'locationTypeOrder' => LocationTypeDefinition::TECHNICAL_NAME_ORDER,
            ],
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
