<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1660643832AddOrderConfigurationVersion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1660643832;
    }

    public function update(Connection $connection): void
    {
        // Delete all OrderConfigurations. The indexer will create new OrderConfigurations after the update
        $connection->executeStatement('DELETE FROM `pickware_shopware_extensions_order_configuration`;');

        // Drop the old unique key on (only) `order_id` and add composite unique on `order_id` and `order_version_id`.
        // This way we allow an OrderConfiguration per order version.
        $connection->executeStatement(
            'ALTER TABLE `pickware_shopware_extensions_order_configuration`
                DROP INDEX `pickware_sw_ext_o_configuration.uidx.order`;',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_shopware_extensions_order_configuration`
                ADD CONSTRAINT `pickware_sw_ext_o_configuration.uidx.order` UNIQUE KEY (`order_id`, `order_version_id`);',
        );

        // Add versioning to the OrderConfiguration itself
        $connection->executeStatement(
            'ALTER TABLE `pickware_shopware_extensions_order_configuration`
                ADD COLUMN `version_id` BINARY(16) NOT NULL AFTER `id`;',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_shopware_extensions_order_configuration` DROP PRIMARY KEY;',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_shopware_extensions_order_configuration` ADD PRIMARY KEY (`id`, `version_id`);',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
