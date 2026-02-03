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
use Pickware\PhpStandardLibrary\Json\Json;
use Shopware\Core\Framework\Migration\MigrationStep;

// This configuration is actually not used anymore. See https://github.com/pickware/shopware-plugins/issues/6801
class Migration1719991117AddTaxExemptExportLabelDocumentConfigToStorno extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1719991117;
    }

    public function update(Connection $connection): void
    {
        $connection->transactional(function(Connection $transaction): void {
            $stornoConfig = $transaction->executeQuery(
                <<<SQL
                    SELECT
                        LOWER(HEX(`document_base_config`.`id`)) AS `id`,
                        `document_base_config`.`config` AS `config`
                    FROM `document_base_config`
                    JOIN `document_type` ON `document_base_config`.`document_type_id` = `document_type`.`id`
                    WHERE `document_type`.`technical_name` = :technicalName;
                    SQL,
                ['technicalName' => 'storno'],
            )->fetchAssociative();

            if ($stornoConfig === false) {
                return;
            }

            $config = Json::decodeToArray($stornoConfig['config']);

            if (!isset($config['pickwareErpDisplayTaxExemptExportLabel'])) {
                $config['pickwareErpDisplayTaxExemptExportLabel'] = false;
            }
            if (!isset($config['pickwareErpTaxExemptExportLabelCountryIds'])) {
                $nonEuCountryIds = $transaction->executeQuery(
                    <<<SQL
                        SELECT LOWER(HEX(`id`)) FROM `country` WHERE `iso` NOT IN (
                            'AT', -- Austria
                            'BE', -- Belgium
                            'BG', -- Bulgaria
                            'CY', -- Cyprus
                            'CZ', -- Czech Republic
                            'DE', -- Germany
                            'DK', -- Denmark
                            'EE', -- Estonia
                            'ES', -- Spain
                            'FI', -- Finland
                            'FR', -- France
                            'GR', -- Greece
                            'HR', -- Croatia
                            'HU', -- Hungary
                            'IE', -- Ireland
                            'IT', -- Italy
                            'LT', -- Lithuania
                            'LU', -- Luxembourg
                            'LV', -- Latvia
                            'MT', -- Malta
                            'NL', -- Netherlands
                            'PL', -- Poland
                            'PT', -- Portugal
                            'RO', -- Romania
                            'SE', -- Sweden
                            'SI', -- Slovenia
                            'SK'  -- Slovakia
                        );
                        SQL,
                )->fetchFirstColumn();
                $config['pickwareErpTaxExemptExportLabelCountryIds'] = $nonEuCountryIds;
            }

            if (!isset($config['deliveryCountries'])) {
                $euCountryIds = $transaction->executeQuery(
                    <<<SQL
                        SELECT LOWER(HEX(`id`)) FROM `country` WHERE `iso` IN (
                            'AT', -- Austria
                            'BE', -- Belgium
                            'BG', -- Bulgaria
                            'CY', -- Cyprus
                            'CZ', -- Czech Republic
                            'DE', -- Germany
                            'DK', -- Denmark
                            'EE', -- Estonia
                            'ES', -- Spain
                            'FI', -- Finland
                            'FR', -- France
                            'GR', -- Greece
                            'HR', -- Croatia
                            'HU', -- Hungary
                            'IE', -- Ireland
                            'IT', -- Italy
                            'LT', -- Lithuania
                            'LU', -- Luxembourg
                            'LV', -- Latvia
                            'MT', -- Malta
                            'NL', -- Netherlands
                            'PL', -- Poland
                            'PT', -- Portugal
                            'RO', -- Romania
                            'SE', -- Sweden
                            'SI', -- Slovenia
                            'SK'  -- Slovakia
                        );
                        SQL,
                )->fetchFirstColumn();
                $config['deliveryCountries'] = $euCountryIds;
            }

            $transaction->executeQuery(
                'UPDATE `document_base_config` SET `config` = :config WHERE `id` = :id;',
                [
                    'id' => hex2bin($stornoConfig['id']),
                    'config' => Json::stringify($config),
                ],
            );
        });
    }

    public function updateDestructive(Connection $connection): void {}
}
