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
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1645103528UpdateSupplierOrderPriceFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1645103528;
    }

    public function update(Connection $connection): void
    {
        $this->migrateSupplierOrderPrices($connection);
        $this->migrateSupplierOrderRoundingConfiguration($connection);
        $this->migrateSupplierOrderLineItemPrices($connection);
        $this->migrateExportColumnConfiguration($connection);
    }

    public function updateDestructive(Connection $connection): void {}

    private function migrateSupplierOrderPrices(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_supplier_order` ADD COLUMN `price` JSON DEFAULT NULL AFTER `delivery_date`;',
        );

        $supplierOrders = $connection->fetchAllAssociative(
            'SELECT
                HEX(`id`) AS id,
                `total_net`,
                `total_gross`,
                HEX(`supplier_id`) AS supplier_id,
                HEX(`warehouse_id`) AS warehouse_id,
                HEX(`currency_id`) AS currency_id,
                HEX(`state_id`) AS state_id,
                HEX(`payment_state_id`) AS payment_state_id,
                `number`,
                `order_date_time`,
                `created_at`
            FROM `pickware_erp_supplier_order`',
        );

        if (count($supplierOrders) > 0) {
            /**
             * There are simplifications/defaults values when migrating the order prices:
             * - Supplier orders are now tax status net
             * - The tax rate (calculated tax, tax rule) is calculated by the difference of the net and gross value of the order
             * - No rounding is done since there are only additions/subtractions
             */
            $sqlUpdateValues = [];
            foreach ($supplierOrders as $supplierOrder) {
                $gross = (float) $supplierOrder['total_gross'];
                $net = (float) $supplierOrder['total_net'];
                // Similar to FloatComparator::equals
                if (abs($net) < 0.00000001) {
                    $taxRate = 19.0;
                } else {
                    $taxRate = round(100 * ($gross - $net) / $net, 2);
                }

                // Use direct payload instead of serializing a CartPrice struct, so we don't depend on a PHP class in a
                // static (not changing) migration.
                $cartPrice = [
                    'netPrice' => $net,
                    'totalPrice' => $gross,
                    'positionPrice' => $net,
                    'calculatedTaxes' => [
                        [
                            'tax' => $gross - $net,
                            'taxRate' => $taxRate,
                            'price' => $gross,
                            'extensions' => [],
                        ],
                    ],
                    'taxRules' => [
                        [
                            'taxRate' => $taxRate,
                            'extensions' => [],
                            'percentage' => 100,
                        ],
                    ],
                    'taxStatus' => 'net',
                    'rawTotal' => $gross,
                ];

                $sqlUpdateValues[] = [
                    sprintf('UNHEX("%s")', $supplierOrder['id']),
                    sprintf('\'%s\'', Json::stringify($cartPrice, \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_INVALID_UTF8_IGNORE)),
                    sprintf('\'%s\'', $supplierOrder['total_net']),
                    sprintf('\'%s\'', $supplierOrder['total_gross']),
                    sprintf('UNHEX("%s")', $supplierOrder['supplier_id']),
                    sprintf('UNHEX("%s")', $supplierOrder['warehouse_id']),
                    sprintf('UNHEX("%s")', $supplierOrder['currency_id']),
                    sprintf('UNHEX("%s")', $supplierOrder['state_id']),
                    sprintf('UNHEX("%s")', $supplierOrder['payment_state_id']),
                    sprintf('\'%s\'', $supplierOrder['number']),
                    sprintf('\'%s\'', $supplierOrder['order_date_time']),
                    sprintf('\'%s\'', $supplierOrder['created_at']),
                ];
            }

            // To update all orders by id in a single statement, we need to insert all orders again and use ON DUPLICATE KEY
            // UPDATE. The price field is the only field that changed. But to ensure the INSERT statement is valid, we need
            // to set all other required fields as well.
            $connection->executeStatement(
                'INSERT INTO pickware_erp_supplier_order
            (
                `id`,
                `price`,
                `total_net`,
                `total_gross`,
                `supplier_id`,
                `warehouse_id`,
                `currency_id`,
                `state_id`,
                `payment_state_id`,
                `number`,
                `order_date_time`,
                `created_at`
             )
             VALUES
            ' . implode(
                    ', ',
                    array_map(
                        fn(array $values) => sprintf('(%s)', implode(', ', $values)),
                        $sqlUpdateValues,
                    ),
                ) . ' ON DUPLICATE KEY UPDATE `price` = VALUES(`price`);',
            );
        }

        // Add the remaining columns after prices have been migrated (and `price` values are not null anymore)
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_supplier_order` CHANGE COLUMN `price` `price` JSON NOT NULL;',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_supplier_order`
            ADD COLUMN `amount_total` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`,"$.totalPrice"))) VIRTUAL AFTER `price`,
            ADD COLUMN `amount_net` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`,"$.netPrice"))) VIRTUAL AFTER `amount_total`,
            ADD COLUMN `position_price` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`,"$.positionPrice"))) VIRTUAL AFTER `amount_net`,
            ADD COLUMN `tax_status` VARCHAR(255) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`,"$.taxStatus"))) VIRTUAL AFTER `position_price`;',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_supplier_order`
            DROP COLUMN `total_net`,
            DROP COLUMN `total_gross`;',
        );
    }

    private function migrateSupplierOrderRoundingConfiguration(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_supplier_order`
                ADD COLUMN `item_rounding` JSON DEFAULT NULL AFTER `currency_id`,
                ADD COLUMN `total_rounding` JSON DEFAULT NULL AFTER `item_rounding`;',
        );

        $fallbackRounding = '{"decimals": "2", "interval": 0.01, "roundForNet": true}';
        $connection->executeStatement(
            'UPDATE `pickware_erp_supplier_order`
            INNER JOIN `currency`
                ON `pickware_erp_supplier_order`.`currency_id` = `currency`.`id`
            SET
                `pickware_erp_supplier_order`.`item_rounding` = IFNULL(`currency`.`item_rounding`, :fallbackRounding),
                `pickware_erp_supplier_order`.`total_rounding` = IFNULL(`currency`.`total_rounding`, :fallbackRounding)',
            [
                'fallbackRounding' => $fallbackRounding,
            ],
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_supplier_order`
            CHANGE COLUMN `item_rounding` `item_rounding` JSON NOT NULL,
            CHANGE COLUMN `total_rounding` `total_rounding` JSON NOT NULL;',
        );
    }

    private function migrateSupplierOrderLineItemPrices(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_supplier_order_line_item`
            ADD COLUMN `price` JSON DEFAULT NULL AFTER `price_net`,
            ADD COLUMN `price_definition` JSON DEFAULT NULL AFTER `price`;',
        );

        $supplierOrderLineItems = $connection->fetchAllAssociative(
            'SELECT
                HEX(`id`) AS id,
                `price_net`,
                `price_gross`,
                HEX(`supplier_order_id`) AS supplier_order_id,
                `product_snapshot`,
                `quantity`,
                `created_at`
            FROM `pickware_erp_supplier_order_line_item`',
        );

        if (count($supplierOrderLineItems) > 0) {
            /**
             * There are simplifications/defaults values when migrating the order line item prices:
             * - The tax rate (calculated tax, tax rule) is calculated by the difference of the net and gross value of the line item
             * - No rounding is done since there are only multiplications/subtractions
             */
            $sqlUpdateValues = [];
            foreach ($supplierOrderLineItems as $supplierOrderLineItem) {
                $gross = (float) $supplierOrderLineItem['price_gross'];
                $net = (float) $supplierOrderLineItem['price_net'];
                // Similar to FloatComparator::equals
                if (abs($net) < 0.00000001) {
                    $taxRate = 19.0;
                } else {
                    $taxRate = round(100 * ($gross - $net) / $net, 2);
                }
                $quantity = (int) $supplierOrderLineItem['quantity'];
                $totalNet = $net * $quantity;

                // Use direct payloads instead of serializing a CalculatedPrice and QuantityPriceDefinition struct, so we
                // don't depend on a PHP class in a static (not changing) migration.
                $calculatedPrice = [
                    'unitPrice' => $net,
                    'quantity' => $quantity,
                    'totalPrice' => $totalNet,
                    'calculatedTaxes' => [
                        [
                            'tax' => $gross - $net,
                            'taxRate' => $taxRate,
                            'price' => $totalNet,
                            'extensions' => [],
                        ],
                    ],
                    'taxRules' => [
                        [
                            'taxRate' => $taxRate,
                            'extensions' => [],
                            'percentage' => 100,
                        ],
                    ],
                    'listPrice' => null,
                    'referencePrice' => null,
                ];
                $priceDefinition = [
                    'type' => 'quantity',
                    'price' => $net,
                    'quantity' => $quantity,
                    'taxRules' => [
                        [
                            'taxRate' => $taxRate,
                            'extensions' => [],
                            'percentage' => 100,
                        ],
                    ],
                    'isCalculated' => true,
                    'listPrice' => null,
                    'referencePriceDefinition' => null,
                ];

                $sqlUpdateValues[] = [
                    sprintf('UNHEX("%s")', $supplierOrderLineItem['id']),
                    sprintf('\'%s\'', $supplierOrderLineItem['price_net']),
                    sprintf('\'%s\'', $supplierOrderLineItem['price_gross']),
                    sprintf('\'%s\'', Json::stringify($calculatedPrice, \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_INVALID_UTF8_IGNORE)),
                    sprintf('\'%s\'', Json::stringify($priceDefinition, \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_INVALID_UTF8_IGNORE)),
                    sprintf('UNHEX("%s")', $supplierOrderLineItem['supplier_order_id']),
                    sprintf('\'%s\'', $supplierOrderLineItem['product_snapshot']),
                    sprintf('\'%s\'', $supplierOrderLineItem['quantity']),
                    sprintf('\'%s\'', $supplierOrderLineItem['created_at']),
                ];
            }

            // To update all orders by id in a single statement, we need to insert all orders again and use ON DUPLICATE KEY
            // UPDATE. The price field is the only field that changed. But to ensure the INSERT statement is valid, we need
            // to set all other required fields as well.
            $connection->executeStatement(
                'INSERT INTO pickware_erp_supplier_order_line_item
            (
                `id`,
                `price_net`,
                `price_gross`,
                `price`,
                `price_definition`,
                `supplier_order_id`,
                `product_snapshot`,
                `quantity`,
                `created_at`
             )
             VALUES
            ' . implode(
                    ', ',
                    array_map(
                        fn(array $values) => sprintf('(%s)', implode(', ', $values)),
                        $sqlUpdateValues,
                    ),
                ) . ' ON DUPLICATE KEY UPDATE `price` = VALUES(`price`), `price_definition` = VALUES(`price_definition`);',
            );
        }

        // Add the remaining columns after prices have been migrated (and `price` values are not null anymore)
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_supplier_order_line_item`
            CHANGE COLUMN `price` `price` JSON NOT NULL,
            CHANGE COLUMN `price_definition` `price_definition` JSON NOT NULL;',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_supplier_order_line_item`
            ADD COLUMN `unit_price` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`,"$.unitPrice"))) VIRTUAL AFTER `price_definition`,
            ADD COLUMN `total_price` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`,"$.totalPrice"))) VIRTUAL AFTER `unit_price`;',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_supplier_order_line_item`
            DROP COLUMN `price_net`,
            DROP COLUMN `price_gross`;',
        );
        // To change the quantity column into a virtual column, we need to remove it, then add it again.
        $connection->executeStatement('ALTER TABLE `pickware_erp_supplier_order_line_item` DROP COLUMN `quantity`;');
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_supplier_order_line_item`
            ADD COLUMN `quantity` INT(11) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`,"$.quantity"))) VIRTUAL AFTER `price`;',
        );
    }

    private function migrateExportColumnConfiguration(Connection $connection): void
    {
        // See SupplierOrderExporter::PLUGIN_CONFIG_COLUMNS_KEY
        $existingConfiguration = $connection->fetchAllAssociative(
            'SELECT `configuration_value` AS configValue
            FROM `system_config`
            WHERE `configuration_key` = "PickwareErpStarter.global-plugin-config.supplierOrderCsvExportColumns"',
        );
        if (count($existingConfiguration) === 0) {
            return;
        }

        // "Rename" existing column names in the supplier order export column configuration by removing the old column
        // and adding the new column iff that old column was part of the configuration value before.
        $configValue = Json::decodeToArray($existingConfiguration[0]['configValue']);
        $columns = $configValue[ConfigJsonField::STORAGE_KEY];
        if (in_array('purchase-price-net', $columns)) {
            unset($columns['purchase-price-net']);
            $columns[] = 'unit-price';
        }
        if (in_array('purchase-total-net', $columns)) {
            unset($columns['purchase-total-net']);
            $columns[] = 'total-price';
        }
        $connection->executeStatement(
            'UPDATE `system_config`
            SET `configuration_value` = :newConfigurationValue
            WHERE `configuration_key` = :configurationKey',
            [
                'newConfigurationValue' => Json::stringify([ConfigJsonField::STORAGE_KEY => $columns]),
                // See SupplierOrderExporter::PLUGIN_CONFIG_COLUMNS_KEY
                'configurationKey' => 'PickwareErpStarter.global-plugin-config.supplierOrderCsvExportColumns',
            ],
        );
    }
}
