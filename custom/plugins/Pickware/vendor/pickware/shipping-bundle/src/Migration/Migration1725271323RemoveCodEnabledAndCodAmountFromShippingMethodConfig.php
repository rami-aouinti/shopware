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
use Pickware\PhpStandardLibrary\Json\Json;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1725271323RemoveCodEnabledAndCodAmountFromShippingMethodConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1725271323;
    }

    public function update(Connection $connection): void
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT id, shipment_config
         FROM pickware_shipping_shipping_method_config
         WHERE JSON_EXTRACT(shipment_config, "$.codEnabled") IS NOT NULL
            OR JSON_EXTRACT(shipment_config, "$.codAmount") IS NOT NULL',
        );

        foreach ($rows as $row) {
            $shipmentConfig = Json::decodeToArray($row['shipment_config']);

            // Check and remove 'codEnabled' and 'codAmount'
            if (isset($shipmentConfig['codEnabled'])) {
                unset($shipmentConfig['codEnabled']);
            }
            if (isset($shipmentConfig['codAmount'])) {
                unset($shipmentConfig['codAmount']);
            }

            // Update the row if modifications were made
            $updatedShipmentConfig = Json::stringify($shipmentConfig);
            $connection->update(
                'pickware_shipping_shipping_method_config',
                ['shipment_config' => $updatedShipmentConfig],
                ['id' => $row['id']],
            );
        }
    }

    public function updateDestructive(Connection $connection): void {}
}
