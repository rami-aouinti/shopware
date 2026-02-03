<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle\IdResolver;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use RuntimeException;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderStates;

class CachedStateIdService
{
    /**
     * Internal cache: [stateMachineTechnicalName => [stateTechnicalName => lowercase hex id]]
     *
     * @var array<string, array<string, string>>
     */
    private array $cache = [];

    public function __construct(private readonly Connection $connection) {}

    /**
     * @param string $stateMachineTechnicalName e.g. OrderStates::STATE_MACHINE
     * @param string[] $stateTechnicalNames e.g. ['cancelled', 'completed']
     *
     * @return string[] Lowercase hex ids of the matching state_machine_state rows. Note that these values are not keyed
     * by the technical names, since the id values are used for SQL fetches and the mapping is omitted.
     */
    public function getStateIds(string $stateMachineTechnicalName, array $stateTechnicalNames): array
    {
        if (!array_key_exists($stateMachineTechnicalName, $this->cache)) {
            $this->cache[$stateMachineTechnicalName] = [];
        }

        $requestedStateNames = array_values($stateTechnicalNames);
        $missingStateNames = [];
        foreach ($requestedStateNames as $name) {
            if (!array_key_exists($name, $this->cache[$stateMachineTechnicalName])) {
                $missingStateNames[] = $name;
            }
        }

        if ($missingStateNames !== []) {
            $fetchedStateMachines = $this->connection->fetchAllKeyValue(
                'SELECT
                    `state_machine_state`.`technical_name` AS `name`,
                    LOWER(HEX(`state_machine_state`.`id`)) AS `id`
                FROM `state_machine_state`
                INNER JOIN `state_machine`
                    ON `state_machine`.`id` = `state_machine_state`.`state_machine_id`
                    AND `state_machine`.`technical_name` = :stateMachineName
                WHERE `state_machine_state`.`technical_name` IN (:states)',
                [
                    'states' => $missingStateNames,
                    'stateMachineName' => $stateMachineTechnicalName,
                ],
                [
                    'states' => ArrayParameterType::STRING,
                ],
            );

            foreach ($fetchedStateMachines as $name => $id) {
                $this->cache[$stateMachineTechnicalName][$name] = $id;
            }
        }

        $result = [];
        foreach ($requestedStateNames as $name) {
            if (isset($this->cache[$stateMachineTechnicalName][$name])) {
                $result[] = $this->cache[$stateMachineTechnicalName][$name];
            } else {
                throw new RuntimeException(sprintf(
                    'State with technical name "%s" not found in state machine "%s".',
                    $name,
                    $stateMachineTechnicalName,
                ));
            }
        }

        return $result;
    }

    /**
     * @param string[] $stateTechnicalNames
     *
     * @return string[]
     */
    public function getOrderStateIds(array $stateTechnicalNames): array
    {
        return $this->getStateIds(OrderStates::STATE_MACHINE, $stateTechnicalNames);
    }

    /**
     * @param string[] $stateTechnicalNames
     *
     * @return string[]
     */
    public function getOrderDeliveryStateIds(array $stateTechnicalNames): array
    {
        return $this->getStateIds(OrderDeliveryStates::STATE_MACHINE, $stateTechnicalNames);
    }
}
