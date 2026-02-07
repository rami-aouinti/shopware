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

class Migration1637145145MigrateCarriersToNewConfigSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1637145145;
    }

    public function update(Connection $db): void
    {
        $carriers = $db->fetchAllAssociative(
            'SELECT
                `technical_name`,
                `config_default_values`,
                `config_options`
            FROM `pickware_shipping_carrier`',
        );

        $migratedCarriers = [];
        foreach ($carriers as $carrier) {
            $migratedCarriers[] = [
                'technicalName' => $carrier['technical_name'],
                'configDefaultValues' => ['shipmentConfig' => Json::decodeToArray($carrier['config_default_values'])],
                'configOptions' => ['shipmentConfig' => Json::decodeToArray($carrier['config_options'])],
            ];
        }

        foreach ($migratedCarriers as $migratedCarrier) {
            $db->executeStatement(
                'UPDATE `pickware_shipping_carrier`
                SET `config_default_values` = :configDefaultValues, `config_options` = :configOptions
                WHERE `technical_name` = :technicalName',
                [
                    'configDefaultValues' => Json::stringify($migratedCarrier['configDefaultValues']),
                    'configOptions' => Json::stringify($migratedCarrier['configOptions']),
                    'technicalName' => $migratedCarrier['technicalName'],
                ],
            );
        }
    }

    public function updateDestructive(Connection $connection): void {}
}
