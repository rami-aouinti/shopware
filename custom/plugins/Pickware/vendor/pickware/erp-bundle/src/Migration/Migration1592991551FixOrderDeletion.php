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

/**
 * This migration is necessary for deleting orders, where all stocks for the order delivery positions need to be
 * removed as well.
 */
class Migration1592991551FixOrderDeletion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1592991551;
    }

    // phpcs:disable ShopwarePlugins.Migration.ForeignKeyIndexPair.MissingDropIndex
    // Is already re-created in this migration and we do not touch it retrospectively.
    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stock`
                DROP FOREIGN KEY `pickware_erp_stock.fk.order_delivery_position`',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stock`
                ADD CONSTRAINT `pickware_erp_stock.fk.order_delivery_position`
                    FOREIGN KEY (`order_delivery_position_id`, `order_delivery_position_version_id`)
                    REFERENCES `order_delivery_position` (`id`, `version_id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
