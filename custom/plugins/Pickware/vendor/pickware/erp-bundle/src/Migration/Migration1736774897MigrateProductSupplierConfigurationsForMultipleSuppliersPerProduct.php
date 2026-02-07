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
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\PhpStandardLibrary\Json\Json;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1736774897MigrateProductSupplierConfigurationsForMultipleSuppliersPerProduct extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1736774897;
    }

    public function update(Connection $connection): void
    {
        $this->writeSnapshotOfExistingProductSupplierConfigurationsToSupplierOrderLineItems($connection);
        $this->migrateExistingProductSupplierConfigurationsToNewTable($connection);
        $this->setDefaultSupplierFromExistingProductSupplierConfigurations($connection);
        $this->setProductSupplierConfigurationsOnExistingPurchaseListItems($connection);
        $this->deleteOldProductSupplierConfigurationTable($connection);
        $this->renameNewProductSupplierConfigurationTable($connection);
        $this->renameOldImportExportProfileNames($connection);
    }

    public function writeSnapshotOfExistingProductSupplierConfigurationsToSupplierOrderLineItems(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                UPDATE `pickware_erp_supplier_order_line_item` as `supplierOrderLineItem`
                LEFT JOIN `pickware_erp_product_supplier_configuration` as `productSupplierConfiguration`
                    ON `productSupplierConfiguration`.`product_id` = `supplierOrderLineItem`.`product_id`
                    AND `productSupplierConfiguration`.`product_version_id` = `supplierOrderLineItem`.`product_version_id`
                SET `product_supplier_configuration_snapshot` = JSON_OBJECT(
                    'supplierProductNumber', COALESCE(`productSupplierConfiguration`.`supplier_product_number`, ""),
                    'minPurchase', COALESCE(`productSupplierConfiguration`.`min_purchase`, 0),
                    'purchaseSteps', COALESCE(`productSupplierConfiguration`.`purchase_steps`, 0)
                )
                WHERE `supplierOrderLineItem`.`product_supplier_configuration_snapshot` IS NULL;
                SQL
        );
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_erp_supplier_order_line_item`
                MODIFY `product_supplier_configuration_snapshot` JSON NOT NULL
                SQL
        );
    }

    private function migrateExistingProductSupplierConfigurationsToNewTable(Connection $connection): void
    {
        $connection->executeStatement(
            'INSERT INTO `pickware_erp_product_supplier_configuration_dev` (
                    `id`,
                    `product_id`,
                    `product_version_id`,
                    `supplier_id`,
                    `supplier_product_number`,
                    `min_purchase`,
                    `purchase_steps`,
                    `purchase_prices`,
                    `supplier_is_default`,
                    `created_at`,
                    `updated_at`
                ) SELECT
                    ' . SqlUuid::UUID_V4_GENERATION . ',
                    `oldProductSupplierConfiguration`.`product_id`,
                    `oldProductSupplierConfiguration`.`product_version_id`,
                    `oldProductSupplierConfiguration`.`supplier_id`,
                    `oldProductSupplierConfiguration`.`supplier_product_number`,
                    `oldProductSupplierConfiguration`.`min_purchase`,
                    `oldProductSupplierConfiguration`.`purchase_steps`,
                    COALESCE(`product`.`purchase_prices`, `parentProduct`.`purchase_prices`, :defaultPurchasePrices),
                    TRUE,
                    UTC_TIMESTAMP(3),
                    NULL
                FROM `pickware_erp_product_supplier_configuration` as `oldProductSupplierConfiguration`
                    LEFT JOIN `product`
                        ON `oldProductSupplierConfiguration`.`product_id` = `product`.`id`
                        AND `oldProductSupplierConfiguration`.`product_version_id` = `product`.`version_id`
                    LEFT JOIN `product` as `parentProduct`
                        ON `product`.`parent_id` = `parentProduct`.`id`
                        AND `product`.`parent_version_id` = `parentProduct`.`version_id`
                WHERE `oldProductSupplierConfiguration`.`supplier_id` IS NOT NULL;',
            [
                'defaultPurchasePrices' => Json::stringify([
                    'c' . Defaults::CURRENCY => [
                        'currencyId' => Defaults::CURRENCY,
                        'net' => 0.0,
                        'gross' => 0.0,
                        'linked' => true,
                    ],
                ]),
            ],
        );
    }

    private function setDefaultSupplierFromExistingProductSupplierConfigurations(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                UPDATE `pickware_erp_pickware_product` as `pickwareProduct`
                LEFT JOIN `pickware_erp_product_supplier_configuration` as `oldProductSupplierConfiguration`
                    ON `oldProductSupplierConfiguration`.`product_id` = `pickwareProduct`.`product_id`
                    AND `oldProductSupplierConfiguration`.`product_version_id` = `pickwareProduct`.`product_version_id`
                SET `pickwareProduct`.`default_supplier_id` = `oldProductSupplierConfiguration`.`supplier_id`
                WHERE `oldProductSupplierConfiguration`.`supplier_id` IS NOT NULL
                SQL
        );
    }

    private function setProductSupplierConfigurationsOnExistingPurchaseListItems(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                UPDATE `pickware_erp_purchase_list_item` as `purchaseListItem`
                JOIN `pickware_erp_product_supplier_configuration_dev` as `productSupplierConfiguration`
                    ON `productSupplierConfiguration`.`product_id` = `purchaseListItem`.`product_id`
                    AND `productSupplierConfiguration`.`product_version_id` = `purchaseListItem`.`product_version_id`
                SET `purchaseListItem`.`product_supplier_configuration_dev_id` = `productSupplierConfiguration`.`id`
                SQL
        );
    }

    private function deleteOldProductSupplierConfigurationTable(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE `pickware_erp_product_supplier_configuration`');
    }

    private function renameNewProductSupplierConfigurationTable(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `pickware_erp_purchase_list_item` DROP FOREIGN KEY `pickware_erp_purchase_list_item.fk.product_supplier_conf`');
        $connection->executeStatement('ALTER TABLE `pickware_erp_purchase_list_item` DROP INDEX `pickware_erp_purchase_list_item.uidx.product_supplier_conf`');
        $connection->executeStatement('RENAME TABLE `pickware_erp_product_supplier_configuration_dev` TO `pickware_erp_product_supplier_configuration`');
        $connection->executeStatement('ALTER TABLE `pickware_erp_purchase_list_item` CHANGE COLUMN `product_supplier_configuration_dev_id` `product_supplier_configuration_id` BINARY(16) DEFAULT NULL');
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_erp_purchase_list_item`
                ADD CONSTRAINT `pickware_erp_purchase_list_item.fk.product_supplier_conf`
                    FOREIGN KEY (`product_supplier_configuration_id`)
                    REFERENCES `pickware_erp_product_supplier_configuration` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE;
                SQL
        );
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_erp_purchase_list_item`
                ADD UNIQUE INDEX `pickware_erp_purchase_list_item.uidx.product_supplier_conf` (`product_supplier_configuration_id`);
                SQL
        );
    }

    private function renameOldImportExportProfileNames(Connection $connection): void
    {
        // The import export profile might not exist yet since it's created by an installation step which are executed
        // after migrations. If it doesn't exist, the next UPDATE statement might fail due to a foreign key constraint.
        $connection->executeStatement(
            <<<SQL
                INSERT INTO `pickware_erp_import_export_profile` (`technical_name`)
                VALUES ("product-supplier-configuration-list-item")
                ON DUPLICATE KEY UPDATE `technical_name` = `technical_name`
                SQL
        );
        $connection->executeStatement('UPDATE `pickware_erp_import_export` SET `profile_technical_name` = "product-supplier-configuration-list-item" WHERE `profile_technical_name` = "product-supplier-configuration"');
        $connection->executeStatement('DELETE FROM `pickware_erp_import_export_profile` WHERE `technical_name` = "product-supplier-configuration"');
    }
}
