<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Installation\Steps;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareErpStarter\Config\Config;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\SpecialStockLocationDefinition;
use Shopware\Core\Defaults;
use Throwable;

class InitializeStockInstallationStep
{
    private readonly Config $config;

    public function __construct(private readonly Connection $db)
    {
        $this->config = new Config($this->db);
    }

    public function install(): void
    {
        $this->db->beginTransaction();

        try {
            if (!$this->config->isStockInitialized()) {
                // Initialize stock only if it didn't happen already, for example after a plugin-reinstall with prior
                // safe uninstall
                $this->initializeStock();
            }

            $this->config->setStockInitialized(true);
        } catch (Throwable $e) {
            $this->db->rollBack();

            throw $e;
        }

        $this->db->commit();
    }

    /**
     * Uses the `product.stock` that is used by Shopware to initialize stock (stock movements, warehouse stock, physical
     * stock) used in Pickware.
     *
     * We are aware that as of SW 6.6.0 the `product.stock` is the _available_ stock and not the physical stock. For
     * simplicity reasons we still use the pre-erp-installation available stock to initialize the physical stock here.
     * See also https://github.com/pickware/shopware-plugins/issues/7479
     * Afterwards, the InternalReservedStockUpdater should run. If there are open orders that would reserve stock, the
     * available stock (and `product.stock`) will DECREASE after this stock initialization.
     */
    private function initializeStock(): void
    {
        $destinationWarehouse = $this->db->fetchAssociative(
            'SELECT
                LOWER(HEX(`id`)) as `id`,
                `name`,
                `code`
            FROM `pickware_erp_warehouse`
            WHERE `id` = UNHEX(:defaultWarehouseId)',
            [
                'defaultWarehouseId' => $this->config->getDefaultWarehouseId(),
            ],
        );
        $warehouseSnapshot = [
            'code' => $destinationWarehouse['code'],
            'name' => $destinationWarehouse['name'],
        ];

        $this->db->executeStatement(
            'INSERT INTO pickware_erp_stock_movement (
                `id`,
                `product_id`,
                `product_version_id`,
                `quantity`,
                `source_location_type_technical_name`,
                `source_special_stock_location_technical_name`,
                `source_location_snapshot`,
                `destination_location_type_technical_name`,
                `destination_warehouse_id`,
                `destination_location_snapshot`,
                `comment`,
                `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                id,
                version_id,
                stock,
                :specialStockLocationTechnicalName,
                :stockInitializationTechnicalName,
                NULL,
                :warehouseTechnicalName,
                UNHEX(:warehouseId),
                :warehouseSnapshot,
                "",
                UTC_TIMESTAMP(3)
            FROM product
            WHERE product.version_id = UNHEX(:liveVersionId)
            AND product.stock != 0',
            [
                'specialStockLocationTechnicalName' => LocationTypeDefinition::TECHNICAL_NAME_SPECIAL_STOCK_LOCATION,
                'stockInitializationTechnicalName' => SpecialStockLocationDefinition::TECHNICAL_NAME_INITIALIZATION,
                'warehouseTechnicalName' => LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE,
                'warehouseId' => $destinationWarehouse['id'],
                'warehouseSnapshot' => Json::stringify($warehouseSnapshot),
                'liveVersionId' => Defaults::LIVE_VERSION,
            ],
        );

        // The StockIndexer does not run in the initial installation+activation of this plugin (anymore). We need to
        // set the initial stock, physical stock and warehouse stock manually.
        $this->db->executeStatement(
            'INSERT INTO pickware_erp_stock (
                `id`,
                `product_id`,
                `product_version_id`,
                `warehouse_id`,
                `quantity`,
                `location_type_technical_name`,
                `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                id,
                version_id,
                UNHEX(:warehouseId),
                stock,
                :warehouseTechnicalName,
                UTC_TIMESTAMP(3)
            FROM product
            WHERE product.version_id = UNHEX(:liveVersionId)
            AND product.stock != 0',
            [
                'warehouseTechnicalName' => LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE,
                'warehouseId' => $destinationWarehouse['id'],
                'liveVersionId' => Defaults::LIVE_VERSION,
            ],
        );
        $this->db->executeStatement(
            'INSERT INTO pickware_erp_warehouse_stock (
                `id`,
                `product_id`,
                `product_version_id`,
                `warehouse_id`,
                `quantity`,
                `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                id,
                version_id,
                UNHEX(:warehouseId),
                stock,
                UTC_TIMESTAMP(3)
            FROM product
            WHERE product.version_id = UNHEX(:liveVersionId)
            AND product.stock != 0',
            [
                'warehouseId' => $destinationWarehouse['id'],
                'liveVersionId' => Defaults::LIVE_VERSION,
            ],
        );
        $this->db->executeStatement(
            'INSERT INTO pickware_erp_pickware_product (
                `id`,
                `product_id`,
                `product_version_id`,
                `physical_stock`,
                `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                id,
                version_id,
                stock,
                UTC_TIMESTAMP(3)
            FROM product
            WHERE product.version_id = UNHEX(:liveVersionId)
            AND product.stock != 0',
            [
                'liveVersionId' => Defaults::LIVE_VERSION,
            ],
        );
    }
}
