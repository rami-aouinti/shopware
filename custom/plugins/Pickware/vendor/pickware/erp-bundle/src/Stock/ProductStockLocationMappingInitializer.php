<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\RetryableTransaction;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;

class ProductStockLocationMappingInitializer
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Creates a product stock location mapping for all stocks that have none yet and are of type warehouse or
     * bin_location.
     */
    public function ensureProductStockLocationMappingExistsForAllStocks(): void
    {
        $this->db->executeStatement(
            'INSERT INTO `pickware_erp_product_stock_location_mapping` (
                    `id`,
                    `product_id`,
                    `product_version_id`,
                    `warehouse_id`,
                    `bin_location_id`,
                    `stock_id`,
                    `stock_location_type`,
                    `created_at`
                ) SELECT
                    ' . SqlUuid::UUID_V4_GENERATION . ' AS `id`,
                    `stock`.`product_id` AS `product_id`,
                    `stock`.`product_version_id` AS `product_version_id`,
                    `stock`.`warehouse_id` AS `warehouse_id`,
                    `stock`.`bin_location_id` AS `bin_location_id`,
                    `stock`.`id` AS `stock_id`,
                    `stock`.`location_type_technical_name` AS `stock_location_type`,
                    UTC_TIMESTAMP(3) AS `created_at`
                FROM `pickware_erp_stock` `stock`
                WHERE `stock`.`location_type_technical_name` in (:relevantStockLocationTypes)
                ON DUPLICATE KEY UPDATE
                    `pickware_erp_product_stock_location_mapping`.`stock_id` = VALUES(`stock_id`),
                    `pickware_erp_product_stock_location_mapping`.`updated_at` = UTC_TIMESTAMP(3)',
            [
                'relevantStockLocationTypes' => [
                    LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE,
                    LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION,
                ],
            ],
            ['relevantStockLocationTypes' => ArrayParameterType::STRING],
        );
    }

    /**
     * @param String[] $stockIds
     */
    public function ensureProductStockLocationMappingsExistForStockIds(array $stockIds): void
    {
        RetryableTransaction::retryable($this->db, function() use ($stockIds): void {
            $this->db->executeStatement(
                'INSERT INTO `pickware_erp_product_stock_location_mapping` (
                    `id`,
                    `product_id`,
                    `product_version_id`,
                    `warehouse_id`,
                    `bin_location_id`,
                    `stock_id`,
                    `stock_location_type`,
                    `created_at`
                ) SELECT
                    ' . SqlUuid::UUID_V4_GENERATION . ' AS `id`,
                    `stock`.`product_id` AS `product_id`,
                    `stock`.`product_version_id` AS `product_version_id`,
                    `stock`.`warehouse_id` AS `warehouse_id`,
                    `stock`.`bin_location_id` AS `bin_location_id`,
                    `stock`.`id` AS `stock_id`,
                    `stock`.`location_type_technical_name` AS `stock_location_type`,
                    UTC_TIMESTAMP(3) AS `created_at`
                FROM `pickware_erp_stock` `stock`
                WHERE `stock`.`id` IN (:stockIds)
                    AND (`stock`.`location_type_technical_name` in (:relevantStockLocationTypes))
                ON DUPLICATE KEY UPDATE
                    `pickware_erp_product_stock_location_mapping`.`stock_id` = VALUES(`stock_id`),
                    `pickware_erp_product_stock_location_mapping`.`updated_at` = UTC_TIMESTAMP(3)',
                [
                    'stockIds' => array_map('hex2bin', $stockIds),
                    'relevantStockLocationTypes' => [
                        LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE,
                        LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION,
                    ],
                ],
                [
                    'stockIds' => ArrayParameterType::STRING,
                    'relevantStockLocationTypes' => ArrayParameterType::STRING,
                ],
            );
        });
    }
}
