<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Migration;

use Doctrine\DBAL\Connection;
use Pickware\DhlExpressBundle\Installation\DhlExpressCarrier;
use Pickware\PhpStandardLibrary\Json\Json;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1769176344RemoveUnsupportedIncotermsFromShippingMethodConfigs extends MigrationStep
{
    private const REMOVED_INCOTERMS = [
        'CPT',
        'CIP',
        'DPU',
        'FAS',
        'FOB',
        'CFR',
        'CFI',
        'DAF',
        'DAT',
        'DEQ',
        'DES',
    ];

    public function getCreationTimestamp(): int
    {
        return 1769176344;
    }

    public function update(Connection $connection): void
    {
        $shippingMethodConfigs = $connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(id)) AS id,
                shipment_config AS shipmentConfig,
                return_shipment_config AS returnShipmentConfig
            FROM `pickware_shipping_shipping_method_config`
            WHERE carrier_technical_name = :carrierTechnicalName',
            ['carrierTechnicalName' => DhlExpressCarrier::TECHNICAL_NAME],
        );

        foreach ($shippingMethodConfigs as $shippingMethodConfig) {
            $shipmentConfig = Json::decodeToArray($shippingMethodConfig['shipmentConfig']);
            $returnShipmentConfig = Json::decodeToArray($shippingMethodConfig['returnShipmentConfig']);

            $shipmentConfigChanged = $this->removeUnsupportedIncoterm($shipmentConfig);
            $returnShipmentConfigChanged = $this->removeUnsupportedIncoterm($returnShipmentConfig);

            if (!$shipmentConfigChanged && !$returnShipmentConfigChanged) {
                continue;
            }

            $connection->executeStatement(
                'UPDATE `pickware_shipping_shipping_method_config`
                SET
                    shipment_config = :shipmentConfig,
                    return_shipment_config = :returnShipmentConfig
                WHERE id = :id',
                [
                    'shipmentConfig' => Json::stringify($shipmentConfig),
                    'returnShipmentConfig' => Json::stringify($returnShipmentConfig),
                    'id' => hex2bin($shippingMethodConfig['id']),
                ],
            );
        }
    }

    public function updateDestructive(Connection $connection): void {}

    /**
     * @param array<string, mixed> $config
     */
    private function removeUnsupportedIncoterm(array &$config): bool
    {
        if (!array_key_exists('incoterm', $config)) {
            return false;
        }

        if (!in_array($config['incoterm'], self::REMOVED_INCOTERMS, true)) {
            return false;
        }

        $config['incoterm'] = null;

        return true;
    }
}
