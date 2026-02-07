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
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1715603573UpdateDocumentPrintingConfigSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1715603573;
    }

    public function update(Connection $connection): void
    {
        // Get all entries of document printing config
        $documentPrintingConfigs = $connection->fetchAllAssociative(
            <<<SQL
                SELECT *
                FROM `pickware_wms_document_printing_config`;
                SQL,
        );

        // Add new columns
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_wms_document_printing_config`
                ADD COLUMN `document_type_id` BINARY(16) AFTER `shipping_method_id`,
                ADD COLUMN `copies` INT(11) DEFAULT 1 CHECK (`copies` > 0) AFTER `document_type_id`,
                MODIFY COLUMN `copies_of_invoices` INT(11) NULL,
                MODIFY COLUMN `copies_of_delivery_notes` INT(11) NULL,
                DROP FOREIGN KEY `pickware_wms_document_printing_config.fk.shipping_method`,
                DROP INDEX `pickware_wms_document_printing_config.uidx.shipping_method`;
                SQL,
        );

        $connection->transactional(function(Connection $connection) use ($documentPrintingConfigs): void {
            // Get all shipping methods IDs that have no document printing config
            $shippingMethodIdsWithoutConfig = $connection->fetchFirstColumn(
                <<<SQL
                    SELECT `id`
                    FROM `shipping_method`
                    WHERE `id` NOT IN (
                        SELECT `shipping_method_id`
                        FROM `pickware_wms_document_printing_config`
                    );
                    SQL,
            );

            // Get document type ID of `invoice`
            $invoiceDocumentTypeId = $connection->fetchOne(
                <<<SQL
                    SELECT `id`
                    FROM `document_type`
                    WHERE `technical_name` = :technicalName;
                    SQL,
                ['technicalName' => 'invoice'],
            );

            // Get document type ID of `delivery_note`
            $deliveryNoteDocumentTypeId = $connection->fetchOne(
                <<<SQL
                    SELECT `id`
                    FROM `document_type`
                    WHERE `technical_name` = :technicalName;
                    SQL,
                ['technicalName' => 'delivery_note'],
            );

            // Delete all legacy entries
            $connection->executeStatement(
                <<<SQL
                    DELETE FROM `pickware_wms_document_printing_config`;
                    SQL,
            );

            // Migrate existing data. Only entries with a copies > 0 are migrated.
            $migratedDocumentPrintingConfigs = [];
            foreach ($documentPrintingConfigs as $documentPrintingConfig) {
                $newDocumentPrintingConfig = [
                    'shippingMethodId' => $documentPrintingConfig['shipping_method_id'],
                    'createdAt' => $documentPrintingConfig['created_at'],
                    'updatedAt' => $documentPrintingConfig['updated_at'],
                ];

                if ($documentPrintingConfig['copies_of_invoices'] > 0 && $invoiceDocumentTypeId !== false) {
                    $migratedDocumentPrintingConfigs[] = [
                        'id' => Uuid::randomBytes(),
                        'documentTypeId' => $invoiceDocumentTypeId,
                        'copies' => $documentPrintingConfig['copies_of_invoices'],
                        ...$newDocumentPrintingConfig,
                    ];
                }
                if ($documentPrintingConfig['copies_of_delivery_notes'] > 0 && $deliveryNoteDocumentTypeId !== false) {
                    $migratedDocumentPrintingConfigs[] = [
                        'id' => Uuid::randomBytes(),
                        'documentTypeId' => $deliveryNoteDocumentTypeId,
                        'copies' => $documentPrintingConfig['copies_of_delivery_notes'],
                        ...$newDocumentPrintingConfig,
                    ];
                }
            }

            foreach ($migratedDocumentPrintingConfigs as $documentPrintingConfig) {
                $connection->executeStatement(
                    <<<SQL
                        INSERT INTO `pickware_wms_document_printing_config` (
                            `id`,
                            `shipping_method_id`,
                            `document_type_id`,
                            `copies`,
                            `created_at`,
                            `updated_at`
                        ) VALUES (
                            :id,
                            :shippingMethodId,
                            :documentTypeId,
                            :copies,
                            :createdAt,
                            :updatedAt
                        );
                        SQL,
                    $documentPrintingConfig,
                );
            }

            // Set the default value for every shipping method that did not have a config yet.
            if ($deliveryNoteDocumentTypeId !== false) {
                foreach ($shippingMethodIdsWithoutConfig as $shippingMethodId) {
                    $connection->executeStatement(
                        <<<SQL
                            INSERT INTO `pickware_wms_document_printing_config` (
                                `id`,
                                `shipping_method_id`,
                                `document_type_id`,
                                `copies`,
                                `created_at`
                            ) VALUES (
                                :id,
                                :shippingMethodId,
                                :documentTypeId,
                                :copies,
                                UTC_TIMESTAMP(3)
                            );
                            SQL,
                        [
                            'id' => Uuid::randomBytes(),
                            'shippingMethodId' => $shippingMethodId,
                            'documentTypeId' => $deliveryNoteDocumentTypeId,
                            'copies' => 1,
                        ],
                    );
                }
            }
        });

        // Apply new schema
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_wms_document_printing_config`
                MODIFY COLUMN `document_type_id` BINARY(16) NOT NULL,
                MODIFY COLUMN `copies` INT(11) NOT NULL DEFAULT 1 CHECK (`copies` > 0),
                DROP COLUMN `copies_of_invoices`,
                DROP COLUMN `copies_of_delivery_notes`,
                -- The name of the unique index is shortened to respect the 64 character limit
                ADD UNIQUE INDEX `pickware_wms_document_printing_config.uidx.ship_method_doc_type` (`shipping_method_id`, `document_type_id`),
                ADD CONSTRAINT `pickware_wms_document_printing_config.fk.document_type`
                    FOREIGN KEY (`document_type_id`)
                    REFERENCES `document_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
