<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Database;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Pickware\PickwareErpStarter\Config\GlobalPluginConfig;

class MariaDBOptimizerDisabler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly GlobalPluginConfig $globalPluginConfig,
    ) {}

    /**
     * @template ReturnValue
     * @param callable():ReturnValue $callback
     * @return ReturnValue
     */
    public function runWithDisabledQueryOptimizationIfConfigured(callable $callback)
    {
        $disableMariaDbQueryOptimizer = $this->globalPluginConfig->isMariaDbQueryOptimizerDisabled();
        if ($disableMariaDbQueryOptimizer) {
            return $this->runWithDisabledQueryOptimization($this->connection, $callback);
        }

        return $callback();
    }

    private function runWithDisabledQueryOptimization(Connection $connection, Closure $closure)
    {
        $optimizerSwitchVariable = $connection->fetchAllAssociative('SHOW VARIABLES LIKE "optimizer_switch"');
        $optimizerSwitchOptimizations = explode(',', $optimizerSwitchVariable[0]['Value']);
        foreach ($optimizerSwitchOptimizations as $optimizerSwitchOptimization) {
            $optimization = explode('=', $optimizerSwitchOptimization);
            if ($optimization[0] === 'split_materialized') {
                $initialSplitMaterializeValue = $optimization[1];
                break;
            }
        }

        try {
            $connection->executeStatement('SET optimizer_switch="split_materialized=off"');
        } catch (Exception) {
            // In the case that the above Statement fails we expect that it failed because this was run on a MySQL
            // Database which does not have the split_materialized option and does not need any performance optimization
            // To not crash any other processes we simply return the result of the closure like it would have been
            // without the setting active.
            // More information here: https://github.com/pickware/shopware-plugins/issues/5250#issuecomment-1985431704
            return $closure();
        }

        $res = $closure();

        $connection->executeStatement(sprintf(
            'SET optimizer_switch="split_materialized=%s"',
            $initialSplitMaterializeValue ?? 'default',
        ));

        return $res;
    }
}
