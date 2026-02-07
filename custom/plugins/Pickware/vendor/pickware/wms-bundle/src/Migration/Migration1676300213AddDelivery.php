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

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Pickware\DalBundle\BulkInsert\Query as DoctrineBulkInsertQuery;
use Pickware\DalBundle\Sql\SqlUuid;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

// phpcs:disable ShopwarePlugins.Migration.ForeignKeyIndexPair.MissingDropIndex
// (false positive) columns and indexes are dropped right after dropping the foreign keys but the names are different
class Migration1676300213AddDelivery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1676300213;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_wms_delivery` (
                `id` BINARY(16) NOT NULL,
                `picking_process_id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `order_version_id` BINARY(16) NOT NULL,
                `state_id` BINARY(16) NOT NULL,
                `stock_container_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_wms_delivery.uidx.stock_container` (`stock_container_id`),
                CONSTRAINT `pickware_wms_delivery.fk.picking_process`
                    FOREIGN KEY (`picking_process_id`)
                    REFERENCES `pickware_wms_picking_process` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_wms_delivery.fk.order`
                    FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_wms_delivery.fk.state`
                    FOREIGN KEY (`state_id`)
                    REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_wms_delivery.fk.stock_container`
                    FOREIGN KEY (`stock_container_id`)
                    REFERENCES `pickware_erp_stock_container` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
                ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );

        $connection->transactional(fn(Connection $connection) => $this->moveInformationToDelivery($connection));

        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process`
            DROP FOREIGN KEY `pickware_wms_picking_process.fk.order`,
            DROP FOREIGN KEY `pickware_wms_picking_process.fk.stock_container`,
            DROP COLUMN `order_id`,
            DROP COLUMN `order_version_id`,
            DROP COLUMN `stock_container_id`',
        );

        $this->remapTrackingCodesToDelivery($connection);
        $this->remapPickwareDocumentsToDelivery($connection);
        $this->remapOrderDocumentsToDelivery($connection);
    }

    public function updateDestructive(Connection $connection): void {}

    private function moveInformationToDelivery(Connection $connection): void
    {
        // Usually we do not create the states in migrations but in the installer, but here the states of the picking
        // process are migrated to the delivery. Before we can do that, the states need to be created.
        $deliveryStateMachineId = $this->createDeliveryStateMachine($connection);

        $uuidGeneration = SqlUuid::UUID_V4_GENERATION;
        $connection->executeStatement(
            "INSERT INTO `pickware_wms_delivery` (
                `id`,
                `picking_process_id`,
                `order_id`,
                `order_version_id`,
                `state_id`,
                `stock_container_id`,
                `created_at`,
                `updated_at`
            ) SELECT
                {$uuidGeneration},
                `picking_process`.`id`,
                `picking_process`.`order_id`,
                `picking_process`.`order_version_id`,
                `delivery_state`.`id`,
                `picking_process`.`stock_container_id`,
                `picking_process`.`created_at`,
                UTC_TIMESTAMP(3)
            FROM `pickware_wms_picking_process` `picking_process`
            INNER JOIN `state_machine_state` `picking_process_state`
                ON `picking_process_state`.`id` = `picking_process`.`state_id`
            INNER JOIN `state_machine_state` `delivery_state`
                ON `delivery_state`.`state_machine_id` = :deliveryStateMachineId
                AND `delivery_state`.`technical_name` = IF(
                    `picking_process_state`.`technical_name` = 'deferred',
                    'in_progress',
                    `picking_process_state`.`technical_name`
                )",
            ['deliveryStateMachineId' => hex2bin($deliveryStateMachineId)],
        );

        $pickedStateId = $connection->fetchOne(
            'SELECT
                LOWER(HEX(`state_machine_state`.`id`))
            FROM `state_machine_state`
            INNER JOIN `state_machine` ON `state_machine_state`.`state_machine_id` = `state_machine`.`id`
            WHERE `state_machine_state`.`technical_name` = "picked"
                AND `state_machine`.`technical_name` = "pickware_wms.picking_process"',
        );
        if ($pickedStateId !== false) {
            // If this is the first installation of the plugin, the state does not exist yet.
            $connection->executeStatement(
                'UPDATE `pickware_wms_picking_process`
                INNER JOIN `state_machine_state`
                    ON `pickware_wms_picking_process`.`state_id` = `state_machine_state`.`id`
                SET `state_id` = :pickedStateId
                WHERE `state_machine_state`.`technical_name` IN ("shipped", "documents_created")',
                ['pickedStateId' => hex2bin($pickedStateId)],
            );
        }
    }

    private function createDeliveryStateMachine(Connection $connection): string
    {
        $deliveryStateMachineId = Uuid::randomHex();
        $deliveryStateTechnicalNames = [
            'in_progress',
            'picked',
            'documents_created',
            'shipped',
            'cancelled',
        ];
        $connection->insert(
            'state_machine',
            [
                'id' => hex2bin($deliveryStateMachineId),
                'technical_name' => 'pickware_wms.delivery',
                'created_at' => new DateTimeImmutable('now'),
            ],
            [
                'id' => Types::BINARY,
                'technical_name' => Types::STRING,
                'created_at' => Types::DATE_IMMUTABLE,
            ],
        );
        (new DoctrineBulkInsertQuery($connection))->execute(
            'state_machine_state',
            array_map(
                fn(string $stateTechnicalName) => [
                    'id' => Uuid::randomBytes(),
                    'state_machine_id' => hex2bin($deliveryStateMachineId),
                    'technical_name' => $stateTechnicalName,
                    'created_at' => new DateTimeImmutable('now'),
                ],
                $deliveryStateTechnicalNames,
            ),
            [
                'id' => Types::BINARY,
                'state_machine_id' => Types::BINARY,
                'technical_name' => Types::STRING,
                'created_at' => Types::DATE_IMMUTABLE,
            ],
        );

        return $deliveryStateMachineId;
    }

    private function remapTrackingCodesToDelivery(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process_tracking_code`
                ADD COLUMN `delivery_id` BINARY(16) NULL AFTER `picking_process_id`',
        );
        $connection->executeStatement(
            'UPDATE `pickware_wms_picking_process_tracking_code` `tracking_code`
            INNER JOIN `pickware_wms_delivery` `delivery`
                ON `tracking_code`.`picking_process_id` = `delivery`.`picking_process_id`
            SET `tracking_code`.`delivery_id` = `delivery`.`id`',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process_tracking_code`
                DROP FOREIGN KEY `pw_wms_picking_process_tracking_code.fk.picking_process`,
                DROP COLUMN `picking_process_id`,
                MODIFY `delivery_id` BINARY(16) NOT NULL,
                ADD FOREIGN KEY `pickware_wms_picking_process_tracking_code.fk.delivery` (`delivery_id`)
                    REFERENCES `pickware_wms_delivery` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE',
        );
        $connection->executeStatement(
            /** @lang SQL */
            'RENAME TABLE
                `pickware_wms_picking_process_tracking_code` TO `pickware_wms_delivery_tracking_code`',
        );
    }

    private function remapPickwareDocumentsToDelivery(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process_document_mapping`
                ADD COLUMN `delivery_id` BINARY(16) NULL AFTER `picking_process_id`',
        );
        $connection->executeStatement(
            'UPDATE `pickware_wms_picking_process_document_mapping` document_mapping
            INNER JOIN `pickware_wms_delivery` `delivery`
                ON `document_mapping`.`picking_process_id` = `delivery`.`picking_process_id`
            SET `document_mapping`.`delivery_id` = `delivery`.`id`',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process_document_mapping`
                DROP FOREIGN KEY `pw_wms_picking_process_document_map.fk.process`,
                DROP PRIMARY KEY,
                DROP COLUMN `picking_process_id`,
                MODIFY `delivery_id` BINARY(16) NOT NULL,
                ADD FOREIGN KEY `pw_wms_delivery_document_mapping.fk.delivery` (delivery_id)
                    REFERENCES `pickware_wms_delivery` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                ADD PRIMARY KEY (`document_id`, `delivery_id`)',
        );

        $connection->executeStatement(
            /** @lang SQL */
            'RENAME TABLE
                `pickware_wms_picking_process_document_mapping` TO `pickware_wms_delivery_document_mapping`',
        );
    }

    private function remapOrderDocumentsToDelivery(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process_order_document_mapping`
                ADD COLUMN `delivery_id` BINARY(16) NULL AFTER `picking_process_id`',
        );
        $connection->executeStatement(
            'UPDATE `pickware_wms_picking_process_order_document_mapping` `order_document_mapping`
            INNER JOIN `pickware_wms_delivery` `delivery`
                ON `order_document_mapping`.`picking_process_id` = `delivery`.`picking_process_id`
            SET `order_document_mapping`.`delivery_id` = `delivery`.`id`',
        );
        $connection->executeStatement(
            'ALTER TABLE pickware_wms_picking_process_order_document_mapping
                DROP FOREIGN KEY `pw_wms_picking_process_order_document_map.fk.process`,
                DROP PRIMARY KEY,
                DROP COLUMN `picking_process_id`,
                MODIFY `delivery_id` BINARY(16) NOT NULL,
                ADD FOREIGN KEY `pw_wms_delivery_order_document_map.fk.delivery` (delivery_id)
                    REFERENCES `pickware_wms_delivery` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                ADD PRIMARY KEY (`order_document_id`, `delivery_id`)',
        );
        $connection->executeStatement(
            /** @lang SQL */
            'RENAME TABLE
                `pickware_wms_picking_process_order_document_mapping` TO `pickware_wms_delivery_order_document_mapping`',
        );
    }
}
