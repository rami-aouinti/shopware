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

class Migration1730903023UpdateStocktakeCountingProcessItemsGridUserConfigSetting extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1730903023;
    }

    public function update(Connection $connection): void
    {
        // Update the user config setting for the stocktake counting process items grid to align the 'username' column to the right
        // This is necessary if user has configured the grid before the 'username' column was aligned to the right,
        // because the user config setting overrides the default grid configuration

        $key = 'grid.setting.pw-erp-stocktaking-stocktake-counting-process-items-grid';
        $configs = $connection->fetchAllAssociative('SELECT `id`, `value` FROM `user_config` WHERE `key` = ?', [$key]);

        foreach ($configs as $config) {
            $configArray = Json::decodeToArray($config['value']);
            if (isset($configArray['columns']) && is_array($configArray['columns'])) {
                foreach ($configArray['columns'] as &$column) {
                    if ($column['property'] === 'countingProcess.user.username') {
                        $column['align'] = 'right';
                    }
                }
                $newConfig = Json::stringify($configArray);
                $connection->executeStatement('UPDATE `user_config` SET `value` = ? WHERE `id` = ?', [$newConfig, $config['id']]);
            }
        }
    }

    public function updateDestructive(Connection $connection): void {}
}
