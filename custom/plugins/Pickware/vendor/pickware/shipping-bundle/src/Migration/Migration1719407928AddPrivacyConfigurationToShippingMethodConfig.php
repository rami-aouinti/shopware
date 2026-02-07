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
use Pickware\ShippingBundle\Privacy\DataTransferPolicy;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1719407928AddPrivacyConfigurationToShippingMethodConfig extends MigrationStep
{
    /**
     * Map of carrier technical names to their corresponding config domains.
     */
    private const CARRIER_TO_CONFIG_DOMAIN_MAP = [
        'deutschePost' => 'PickwareDeutschePost.deutsche-post',
        'dhl' => 'PickwareDhl.dhl',
        'dhlExpress' => 'PickwareDhlExpressBundle.dhl-express',
        'dpd' => 'PickwareDpdBundle.dpd',
        'dsv' => 'PickwareDsvBundle.dsv',
        'gls' => 'PickwareGls.gls',
        'sendcloud' => 'PickwareSendcloudBundle.sendcloud',
        'swissPost' => 'PickwareSwissPostBundle.swiss-post',
        'ups' => 'PickwareUpsBundle.ups',
    ];

    public function getCreationTimestamp(): int
    {
        return 1719407928;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_shipping_method_config`
            ADD `privacy_configuration` JSON NULL AFTER `parcel_packing_configuration`;',
        );

        $connection->transactional(function(Connection $transaction): void {
            $shippingMethodConfigCarriers = $transaction->fetchAllAssociative(
                'SELECT LOWER(HEX(`id`)) AS `id`, `carrier_technical_name` FROM `pickware_shipping_shipping_method_config`;',
            );
            $systemConfiguration = $transaction->fetchAllAssociative(
                'SELECT `configuration_key`, `configuration_value` FROM `system_config`;',
            );
            foreach ($shippingMethodConfigCarriers as $shippingMethodConfigCarrier) {
                $carrierTechnicalName = $shippingMethodConfigCarrier['carrier_technical_name'];
                if (!array_key_exists($carrierTechnicalName, self::CARRIER_TO_CONFIG_DOMAIN_MAP)) {
                    continue;
                }
                $gdprAllowEmail = $this->findBooleanConfigValueForCarrier($carrierTechnicalName, $systemConfiguration, 'gdprAllowEmail');
                $gdprAllowPhone = $this->findBooleanConfigValueForCarrier($carrierTechnicalName, $systemConfiguration, 'gdprAllowPhone');
                $privacyConfiguration = [
                    'emailTransferPolicy' => $gdprAllowEmail === false ? DataTransferPolicy::Never : DataTransferPolicy::Always,
                    'isPhoneTransferAllowed' => $gdprAllowPhone ?? true,
                ];
                $transaction->executeStatement(
                    'UPDATE `pickware_shipping_shipping_method_config` SET `privacy_configuration` = :privacyConfiguration WHERE `id` = :id',
                    [
                        'privacyConfiguration' => Json::stringify($privacyConfiguration),
                        'id' => hex2bin($shippingMethodConfigCarrier['id']),
                    ],
                );
            }
            $transaction->executeStatement(
                'UPDATE `pickware_shipping_shipping_method_config` SET `privacy_configuration` = \'{}\'
                WHERE `privacy_configuration` IS NULL;',
            );
        });

        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_shipping_method_config`
            CHANGE `privacy_configuration` `privacy_configuration` JSON NOT NULL;',
        );
    }

    /**
     * @param array<array{string, string}> $systemConfiguration
     */
    private function findBooleanConfigValueForCarrier(
        string $carrierTechnicalName,
        array $systemConfiguration,
        string $key,
    ): ?bool {
        $fullConfigKey = self::CARRIER_TO_CONFIG_DOMAIN_MAP[$carrierTechnicalName] . '.' . $key;
        $configuration = array_filter(
            $systemConfiguration,
            fn(array $configRow) => $configRow['configuration_key'] === $fullConfigKey,
        );
        if (empty($configuration)) {
            return null;
        }

        $value = array_values($configuration)[0]['configuration_value'];

        return filter_var(
            Json::decodeToArray($value)['_value'],
            FILTER_VALIDATE_BOOLEAN,
        );
    }
}
