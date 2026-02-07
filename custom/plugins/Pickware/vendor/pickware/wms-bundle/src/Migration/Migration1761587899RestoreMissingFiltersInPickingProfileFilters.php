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
use RuntimeException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Fixes Migration1720599267MoveWmsSettingsToPickingProfiles and Migration1756316566UpdatePickingProfileFilterStructure
 */
class Migration1761587899RestoreMissingFiltersInPickingProfileFilters extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1761587899;
    }

    public function update(Connection $connection): void
    {
        // All picking profiles that have not been edited since this first migration ran are affected.
        $firstMigrationCreationDate = $connection->fetchOne(
            <<<SQL
                SELECT DATE_FORMAT(FROM_UNIXTIME(`creation_timestamp`), '%Y-%m-%d')
                FROM `migration`
                WHERE `class` = :class;
                SQL,
            ['class' => 'Pickware\\PickwareWms\\Migration\\Migration1720599267MoveWmsSettingsToPickingProfiles'],
        );
        if ($firstMigrationCreationDate === false) {
            return;
        }

        $affectedPickingProfiles = $connection->fetchAllAssociative(
            <<<SQL
                SELECT
                    LOWER(HEX(`id`)) AS `id`,
                    `filter`
                FROM `pickware_wms_picking_profile`
                WHERE `created_at` < :date AND (`updated_at` < :date OR `updated_at` IS NULL);
                SQL,
            ['date' => $firstMigrationCreationDate],
        );
        if (count($affectedPickingProfiles) === 0) {
            return;
        }
        $affectedPickingProfiles = array_map(
            fn(array $row) => [
                'id' => $row['id'],
                'filter' => Json::decodeToArray($row['filter']),
            ],
            $affectedPickingProfiles,
        );

        // Get the state IDs for the default order transaction states, needed for this migration.
        // We use the `OrderTransactionStates` constant here because we want to fetch valid IDs at the time this
        // migration runs.
        $defaultOrderTransactionStateIds = $connection->fetchFirstColumn(
            <<<SQL
                SELECT LOWER(HEX(`state_machine_state`.`id`)) AS `id`
                FROM `state_machine_state`
                LEFT JOIN `state_machine`
                    ON `state_machine_state`.`state_machine_id` = `state_machine`.`id`
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

        // Check the global wms plugin config to determine if a new payment method filter is needed. Since we never
        // deleted this, it should still be around if it was ever changed.
        $additionalPaymentMethodFilter = null;
        $paymentMethodIdsWithPaymentStateOpenConfigValue = $connection->fetchOne(
            <<<SQL
                SELECT `configuration_value` AS `value`
                FROM `system_config`
                WHERE `system_config`.`configuration_key` = :configurationKey;
                SQL,
            ['configurationKey' => 'PickwareWmsBundle.global-plugin-config.paymentMethodIdsAllowedForPickingWithPaymentStateOpen'],
        );
        if ($paymentMethodIdsWithPaymentStateOpenConfigValue !== false) {
            $paymentMethodIdsWithPaymentStateOpen = Json::decodeToArray($paymentMethodIdsWithPaymentStateOpenConfigValue)['_value'];

            // If there are payment methods configured for picking with payment state open, we create an additional
            // payment method filter which is added to every picking profile.
            if (count($paymentMethodIdsWithPaymentStateOpen) > 0) {
                $orderTransactionStateOpenId = $connection->fetchOne(
                    <<<SQL
                        SELECT LOWER(HEX(`state_machine_state`.`id`)) AS `id`
                        FROM `state_machine_state`
                        LEFT JOIN `state_machine`
                            ON `state_machine_state`.`state_machine_id` = `state_machine`.`id`
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
                        'type' => 'multi',
                        'operator' => 'and',
                        'queries' => [
                            [
                                'type' => 'equalsAny',
                                'field' => 'transactions.paymentMethodId',
                                'value' => $paymentMethodIdsWithPaymentStateOpen,
                            ],
                            [
                                'type' => 'equalsAny',
                                'field' => 'transactions.stateId',
                                'value' => [$orderTransactionStateOpenId],
                            ],
                        ],
                    ];
                }
            }
        }

        $connection->transactional(
            function(
                Connection $connection,
            ) use (
                $affectedPickingProfiles,
                $defaultOrderTransactionStateIds,
                $additionalPaymentMethodFilter,
            ): void {
                foreach ($affectedPickingProfiles as $pickingProfile) {
                    $filter = $pickingProfile['filter'];
                    // Sanity check: The old filter structure should always have a multi "and" filter as root.
                    if ($filter['operator'] !== 'and' || $filter['type'] !== 'multi') {
                        continue;
                    }

                    // By default, we just need the filter on the order transaction state.
                    $filterToAdd = [
                        'type' => 'equalsAny',
                        'field' => 'transactions.stateId',
                        'value' => $defaultOrderTransactionStateIds,
                    ];

                    if ($additionalPaymentMethodFilter !== null) {
                        $filterToAdd = [
                            'type' => 'multi',
                            'operator' => 'or',
                            'queries' => [
                                $filterToAdd,
                                $additionalPaymentMethodFilter,
                            ],
                        ];
                    }

                    array_unshift($filter['queries'], $filterToAdd);

                    $connection->executeStatement(
                        <<<SQL
                            UPDATE `pickware_wms_picking_profile`
                            SET `filter` = :filter
                            WHERE `id` = :id;
                            SQL,
                        [
                            'id' => Uuid::fromHexToBytes($pickingProfile['id']),
                            'filter' => Json::stringify($filter),
                        ],
                    );
                }
            },
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
