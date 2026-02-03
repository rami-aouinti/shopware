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
use Doctrine\DBAL\ParameterType;
use Pickware\PhpStandardLibrary\Json\Json;
use RuntimeException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1720599267MoveWmsSettingsToPickingProfiles extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1720599267;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_wms_picking_profile`
                ADD COLUMN `is_partial_delivery_allowed` BOOL NOT NULL DEFAULT TRUE AFTER `name`;
                SQL,
        );

        $pickingProfiles = $connection->fetchAllAssociative(
            <<<SQL
                SELECT
                    LOWER(HEX(`id`)) AS `id`,
                    `filter`
                FROM `pickware_wms_picking_profile`;
                SQL,
        );
        // If there are no picking profiles, nothing needs to be migrated. This for example is the case at a fresh
        // installation because the installation step that adds the default picking profile didn't run yet.
        if (count($pickingProfiles) === 0) {
            return;
        }
        $pickingProfiles = array_map(
            fn(array $row) => [
                'id' => $row['id'],
                'filter' => Json::decodeToArray($row['filter']),
            ],
            $pickingProfiles,
        );

        $selectStateMachineQuery = <<<SQL
            SELECT LOWER(HEX(`state_machine_state`.`id`)) AS `id`
            FROM `state_machine_state`
            LEFT JOIN `state_machine`
                ON `state_machine_state`.`state_machine_id` = `state_machine`.`id`
            SQL;

        // Get the state IDs for the default order transaction states, needed for this migration.
        // We use the `OrderTransactionStates` constant here because we want to fetch valid IDs at the time this
        // migration runs.
        $defaultOrderTransactionStateIds = $connection->fetchFirstColumn(
            <<<SQL
                {$selectStateMachineQuery}
                WHERE
                    `state_machine`.`technical_name` = :stateMachineTechnicalName
                    AND `state_machine_state`.`technical_name` IN (:statePaid, :statePartiallyRefunded)
                SQL,
            [
                'stateMachineTechnicalName' => OrderTransactionStates::STATE_MACHINE,
                'statePaid' => OrderTransactionStates::STATE_PAID,
                'statePartiallyRefunded' => OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
            ],
        );

        // It is impossible to create valid picking profiles without shopware's order transaction states
        if (count($defaultOrderTransactionStateIds) < 2) {
            throw new RuntimeException('Shopware default order transaction states missing.');
        }

        $selectConfigValueQuery = <<<SQL
            SELECT `configuration_value` AS `value`
            FROM `system_config`
            WHERE
                `system_config`.`configuration_key` = :configurationKey;
            SQL;

        // Check the global wms plugin config to determine if a new payment method filter is needed.
        $additionalPaymentMethodFilter = null;
        $configValue = $connection->fetchOne(
            $selectConfigValueQuery,
            ['configurationKey' => 'PickwareWmsBundle.global-plugin-config.paymentMethodIdsAllowedForPickingWithPaymentStateOpen'],
        );
        if ($configValue !== false) {
            $paymentMethodIdsWithPaymentStateOpen = Json::decodeToArray($configValue)['_value'];

            // If there are payment methods configured for picking with payment state open, we create an additional
            // payment method filter which is added to every picking profile.
            if (count($paymentMethodIdsWithPaymentStateOpen) > 0) {
                $orderTransactionStateOpenId = $connection->fetchOne(
                    <<<SQL
                        {$selectStateMachineQuery}
                        WHERE
                            `state_machine`.`technical_name` = :stateMachineTechnicalName
                            AND `state_machine_state`.`technical_name` = :stateOpen
                        SQL,
                    [
                        'stateMachineTechnicalName' => OrderTransactionStates::STATE_MACHINE,
                        'stateOpen' => OrderTransactionStates::STATE_OPEN,
                    ],
                );
                if ($orderTransactionStateOpenId !== false) {
                    $additionalPaymentMethodFilter = [
                        'paymentMethodIds' => $paymentMethodIdsWithPaymentStateOpen,
                        'orderTransactionStateIds' => [$orderTransactionStateOpenId],
                    ];
                }
            }
        }

        $isPartialDeliveryAllowed = true;
        $configValue = $connection->fetchOne(
            $selectConfigValueQuery,
            ['configurationKey' => 'PickwareWmsBundle.global-plugin-config.disallowPartialDeliveries'],
        );
        if ($configValue !== false) {
            $isPartialDeliveryAllowed = !Json::decodeToArray($configValue)['_value'];
        }

        $connection->transactional(
            function(
                Connection $connection,
            ) use (
                $pickingProfiles,
                $defaultOrderTransactionStateIds,
                $additionalPaymentMethodFilter,
                $isPartialDeliveryAllowed,
            ): void {
                foreach ($pickingProfiles as $pickingProfile) {
                    $filter = $pickingProfile['filter'];
                    $filter['paymentMethodFilters'] = [
                        [
                            'paymentMethodIds' => $filter['paymentMethodIds'],
                            'orderTransactionStateIds' => $defaultOrderTransactionStateIds,
                        ],
                    ];
                    if ($additionalPaymentMethodFilter !== null) {
                        $filter['paymentMethodFilters'][] = $additionalPaymentMethodFilter;
                    }
                    unset($filter['paymentMethodIds']);

                    // We willingly ignore the fact that the `_dalFilter` property will be outdated after this
                    // migration. This isn't an issue because the `_dalFilter` is computed every time the filter is json
                    // serialized and is only persisted because of limitations on how the DAL works.
                    $connection->executeStatement(
                        <<<SQL
                            UPDATE `pickware_wms_picking_profile`
                            SET
                                `filter` = :filter,
                                `is_partial_delivery_allowed` = :isPartialDeliveryAllowed
                            WHERE `id` = :id;
                            SQL,
                        [
                            'id' => hex2bin($pickingProfile['id']),
                            'filter' => Json::stringify($filter),
                            'isPartialDeliveryAllowed' => $isPartialDeliveryAllowed,
                        ],
                        [
                            'isPartialDeliveryAllowed' => ParameterType::BOOLEAN,
                        ],
                    );
                }
            },
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
