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
use function Pickware\InstallationLibrary\Migration\ensureCorrectCollationOfColumnForForeignKeyConstraint;
use Shopware\Core\Framework\Migration\MigrationStep;

// phpcs:disable ShopwarePlugins.Migration.ForeignKeyIndexPair.MissingDropIndex
// Is already re-created in this migration and we do not touch it retrospectively.
class Migration1732618608FixPickwareShippingShippingMethodConfigCarrierTechnicalNameDataType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1732618608;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_shipping_method_config`
            DROP FOREIGN KEY `pickware_shipping_shipping_method_config.fk.carrier`',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_shipping_method_config`
            MODIFY COLUMN `carrier_technical_name` VARCHAR(255) NOT NULL',
        );

        ensureCorrectCollationOfColumnForForeignKeyConstraint(
            $connection,
            'pickware_shipping_shipping_method_config',
            'carrier_technical_name',
        );
        ensureCorrectCollationOfColumnForForeignKeyConstraint(
            $connection,
            'pickware_shipping_carrier',
            'technical_name',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_shipping_method_config`
            ADD CONSTRAINT `pickware_shipping_shipping_method_config.fk.carrier`
            FOREIGN KEY (`carrier_technical_name`)
            REFERENCES `pickware_shipping_carrier` (`technical_name`)
            ON DELETE CASCADE
            ON UPDATE CASCADE',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
