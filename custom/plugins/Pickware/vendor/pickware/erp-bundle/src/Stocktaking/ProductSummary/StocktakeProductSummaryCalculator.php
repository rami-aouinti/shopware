<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\ProductSummary;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\Sql\SqlUuid;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class StocktakeProductSummaryCalculator
{
    private Connection $connection;

    public function __construct(Connection $db)
    {
        $this->connection = $db;
    }

    /**
     * Recalculates the stocktake product summaries taking all counting process items from the given stocktakes with the
     * given products into account, except for those listed in the deny-list. Using this list, subscribers reacting to
     * entity deletion can deny recalculation of certain items when they are still present in the database.
     *
     * One example use case is to react to the deletion of counting processes. As the deletion of its items takes place
     * with foreign key constraints, the subscriber cannot recalculate the summaries after deletion. Before deletion
     * however, the items are still present too.
     */
    public function recalculateStocktakeProductSummaries(array $productIds, array $stocktakeIds, array $countingProcessItemDenyList = []): void
    {
        if (count($productIds) === 0 || count($stocktakeIds) === 0) {
            return;
        }

        if (count($countingProcessItemDenyList) === 0) {
            // MySQL does not support empty tuples with NOT IN clauses. In case our deny list is empty, we generate a
            // dummy value here.
            $countingProcessItemDenyList = [Uuid::randomHex()];
        }

        $this->connection->executeStatement(
            'DELETE FROM `pickware_erp_stocktaking_stocktake_product_summary`
            WHERE `product_id` IN (:productIds) AND `stocktake_id` IN (:stocktakeIds)',
            [
                'productIds' => array_map('hex2bin', $productIds),
                'stocktakeIds' => array_map('hex2bin', $stocktakeIds),
            ],
            [
                'productIds' => ArrayParameterType::STRING,
                'stocktakeIds' => ArrayParameterType::STRING,
            ],
        );

        $this->connection->executeStatement(
            'INSERT INTO `pickware_erp_stocktaking_stocktake_product_summary` (
                 id,
                 product_id,
                 product_version_id,
                 stocktake_id,
                 counted_stock,
                 absolute_stock_difference,
                 created_at
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                countingProcessItem.`product_id` as productId,
                countingProcessItem.`product_version_id` as productVersionId,
                stocktake.`id` as stocktakeId,
                SUM(countingProcessItem.`quantity`) as countedStock,
                SUM(countingProcessItem.`quantity`) - IFNULL(warehouseStock.quantity, 0) as absoluteStockDifference,
                UTC_TIMESTAMP(3)
            FROM `pickware_erp_stocktaking_stocktake` stocktake
                LEFT JOIN `pickware_erp_stocktaking_stocktake_counting_process` countingProcess
                    ON stocktake.`id` = countingProcess.`stocktake_id`
                LEFT JOIN `pickware_erp_stocktaking_stocktake_counting_process_item` countingProcessItem
                    ON countingProcess.`id` = countingProcessItem.`counting_process_id`
                LEFT JOIN pickware_erp_warehouse_stock warehouseStock
                    ON warehouseStock.`product_id` = countingProcessItem.`product_id`
                        AND warehouseStock.`warehouse_id` = stocktake.`warehouse_id`
            WHERE
                countingProcessItem.`product_id` IN (:productIds)
                AND countingProcessItem.`id` NOT IN (:countingProcessItemIdDenyList)
                AND stocktake.`id` IN (:stocktakeIds)
                AND stocktake.`is_active` = 1
                AND countingProcessItem.`product_version_id` = :liveVersionId
            GROUP BY countingProcessItem.`product_id`, stocktake.`id`',
            [
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'productIds' => array_map('hex2bin', $productIds),
                'countingProcessItemIdDenyList' => array_map('hex2bin', $countingProcessItemDenyList),
                'stocktakeIds' => array_map('hex2bin', $stocktakeIds),
            ],
            [
                'productIds' => ArrayParameterType::STRING,
                'countingProcessItemIdDenyList' => ArrayParameterType::STRING,
                'stocktakeIds' => ArrayParameterType::STRING,
            ],
        );
    }
}
