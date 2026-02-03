<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Migration;

use Doctrine\DBAL\Connection;
use Pickware\PhpStandardLibrary\Json\Json;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1692088047UpdateShippingLabelCreationConfig extends MigrationStep
{
    private const OLD_CONFIG_KEY = 'PickwareWmsBundle.global-plugin-config.shouldCreateShippingLabelUponCompletionOfPicking';
    private const NEW_CONFIG_KEY = 'PickwareWmsBundle.global-plugin-config.shippingLabelCreationMode';

    public function getCreationTimestamp(): int
    {
        return 1692088047;
    }

    public function update(Connection $connection): void
    {
        $existingConfig = $connection->fetchFirstColumn(
            <<<SQL
                SELECT `configuration_value`
                FROM `system_config`
                WHERE `configuration_key` = :configKey
                SQL,
            [
                'configKey' => self::OLD_CONFIG_KEY,
            ],
        );
        if (empty($existingConfig)) {
            return;
        }

        $existingConfigValue = Json::decodeToArray($existingConfig[0]);
        $newConfigValue = ($existingConfigValue['_value'] === false) ? 'manual' : 'presentDialog';
        $connection->executeStatement(
            <<<SQL
                UPDATE `system_config`
                SET
                    `configuration_key` = :newConfigKey,
                    `configuration_value` = :newConfigValue
                WHERE `configuration_key` = :oldConfigKey
                SQL,
            [
                'newConfigKey' => self::NEW_CONFIG_KEY,
                'newConfigValue' => Json::stringify(['_value' => $newConfigValue]),
                'oldConfigKey' => self::OLD_CONFIG_KEY,
            ],
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
