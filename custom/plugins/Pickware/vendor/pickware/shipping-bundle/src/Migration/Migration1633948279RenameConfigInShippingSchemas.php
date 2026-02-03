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
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1633948279RenameConfigInShippingSchemas extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1633948279;
    }

    /**
     * This migration was shipped to various customers and failed half way through. We fixed and changed this
     * migration to be idempotent now, so the customers can rerun this migration without manual work.
     */
    public function update(Connection $db): void
    {
        $oldCarrierColumns = $db->fetchAllAssociative('SHOW COLUMNS FROM `pickware_shipping_carrier`;');
        $oldShippingMethodConfigColumns = $db->fetchAllAssociative(
            'SHOW COLUMNS FROM `pickware_shipping_shipping_method_config`;',
        );

        $fieldNameExtractionFunction = fn($column) => $column['Field'];
        $oldCarrierColumnNames = array_map($fieldNameExtractionFunction, $oldCarrierColumns);
        $oldShippingMethodConfigColumnNames = array_map($fieldNameExtractionFunction, $oldShippingMethodConfigColumns);

        if (in_array('shipment_config_default_values', $oldCarrierColumnNames, true)) {
            $db->executeStatement(
                '
                ALTER TABLE `pickware_shipping_carrier`
                    CHANGE `shipment_config_default_values` `config_default_values` JSON NOT NULL;
            ',
            );
        }

        if (in_array('shipment_config_options', $oldCarrierColumnNames, true)) {
            $db->executeStatement(
                '
                ALTER TABLE `pickware_shipping_carrier`
                    CHANGE `shipment_config_options` `config_options` JSON NOT NULL;
            ',
            );
        }

        try {
            $db->executeStatement(
                'ALTER TABLE `pickware_shipping_shipping_method_config`
                    DROP CHECK `pickware_shipping_shipping_method_config_chk_1`;',
            );
        } catch (Exception $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
            // Drop check syntax is not supported by mysql versions prior to 8.0.16, but has to be done for mysql
            // versions > 8.0.16 as they do not support renaming a column which is referenced by a check constraint.
        }

        if (in_array('shipment_config', $oldShippingMethodConfigColumnNames, true)) {
            $db->executeStatement(
                '
                ALTER TABLE `pickware_shipping_shipping_method_config`
                    CHANGE `shipment_config` `config` LONGTEXT NOT NULL;
            ',
            );
        }

        $db->executeStatement(
            '
                ALTER TABLE `pickware_shipping_shipping_method_config`
                    ADD CHECK (json_valid(`config`));
            ',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
