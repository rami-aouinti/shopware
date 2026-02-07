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

class Migration1677064840MigrateConfigOptionsToNewConfigSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1677064840;
    }

    public function update(Connection $connection): void
    {
        // JSON cannot have a non-Null default value prior to MySQL 8.0.13
        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_carrier`
                ADD COLUMN `shipment_config_default_values` JSON NULL
                    AFTER `config_options`,
                ADD COLUMN `shipment_config_options` JSON NULL
                    AFTER `shipment_config_default_values`,
                ADD COLUMN `storefront_config_default_values` JSON NULL
                    AFTER `shipment_config_options`,
                ADD COLUMN `storefront_config_options` JSON NULL
                    AFTER `storefront_config_default_values`;',
        );

        $connection->executeStatement(
            'UPDATE `pickware_shipping_carrier`
            SET
                # Values may be NULL if the key does not exist in the object
                `shipment_config_default_values` = IFNULL(JSON_EXTRACT(`config_default_values`, "$.shipmentConfig"), "[]"),
                `shipment_config_options` = IFNULL(JSON_EXTRACT(`config_options`, "$.shipmentConfig"), "{}"),
                `storefront_config_default_values` = IFNULL(JSON_EXTRACT(`config_default_values`, "$.storefrontConfig"), "[]"),
                `storefront_config_options` = IFNULL(JSON_EXTRACT(`config_options`, "$.storefrontConfig"), "{}")',
        );

        $connection->executeStatement('
            ALTER TABLE `pickware_shipping_carrier`
                CHANGE `shipment_config_default_values` `shipment_config_default_values` JSON NOT NULL,
                CHANGE `shipment_config_options` `shipment_config_options` JSON NOT NULL,
                CHANGE `storefront_config_default_values` `storefront_config_default_values` JSON NOT NULL,
                CHANGE `storefront_config_options` `storefront_config_options` JSON NOT NULL;
        ');

        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_carrier`
                DROP COLUMN `config_default_values`,
                DROP COLUMN `config_options`;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_shipping_method_config`
                ADD COLUMN `shipment_config` LONGTEXT NOT NULL
                    AFTER `config`,
                ADD COLUMN `storefront_config` LONGTEXT NOT NULL
                    AFTER `shipment_config`;',
        );

        $connection->executeStatement(
            'UPDATE `pickware_shipping_shipping_method_config`
            SET
                `shipment_config` = `config`,
                `storefront_config` = `config`',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_shipping_method_config`
                ADD CHECK (json_valid(`shipment_config`)),
                ADD CHECK (json_valid(`storefront_config`));',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_shipping_method_config`
                DROP COLUMN `config`;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
