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
 * This migration is necessary for deleting products, where all stocks (pickware_erp_stocks) for the product need to be
 * removed as well.
 */
class Migration1593514482FixProductDeletion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1593514482;
    }

    // phpcs:disable ShopwarePlugins.Migration.ForeignKeyIndexPair.MissingDropIndex
    // Is already re-created in this migration and we do not touch it retrospectively.
    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stock`
                DROP FOREIGN KEY `pickware_erp_stock.fk.product`',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_stock`
                ADD CONSTRAINT `pickware_erp_stock.fk.product`
                    FOREIGN KEY (`product_id`,`product_version_id`)
                    REFERENCES `product` (`id`,`version_id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
