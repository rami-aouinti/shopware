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

class Migration1677078498AddReturnShipmentConfigToCarrierCarriers extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1677078498;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_carrier`
                ADD COLUMN `return_shipment_config_default_values` JSON NULL
                    AFTER `storefront_config_options`,
                ADD COLUMN `return_shipment_config_options` JSON NULL
                    AFTER `return_shipment_config_default_values`;',
        );

        $connection->executeStatement(
            'UPDATE `pickware_shipping_carrier`
                SET
                    return_shipment_config_default_values = "{}",
                    return_shipment_config_options = "{}"',
        );

        $connection->executeStatement('
            ALTER TABLE `pickware_shipping_carrier`
                CHANGE `return_shipment_config_default_values` `return_shipment_config_default_values` JSON NOT NULL,
                CHANGE `return_shipment_config_options` `return_shipment_config_options` JSON NOT NULL;
        ');

        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_shipping_method_config`
                ADD COLUMN `return_shipment_config` JSON NULL
                    AFTER `storefront_config`;',
        );

        $connection->executeStatement(
            'UPDATE `pickware_shipping_shipping_method_config`
                SET `return_shipment_config` = "{}"',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_shipping_method_config`
                CHANGE `return_shipment_config` `return_shipment_config` JSON NOT NULL;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
