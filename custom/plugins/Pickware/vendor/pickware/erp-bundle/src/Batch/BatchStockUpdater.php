<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Shopware\Core\Defaults;

class BatchStockUpdater
{
    public function __construct(
        private readonly Connection $db,
    ) {}

    /**
     * Updates the physical stock for batches of the given products.
     * DEPENDS ON pickware_erp_batch_stock_mapping to have been calculated before for the given products.
     *
     * @param string[] $productIds
     */
    public function calculateBatchStockForProducts(array $productIds): void
    {
        if (empty($productIds)) {
            return;
        }

        $this->db->executeStatement(
            <<<SQL
                UPDATE `pickware_erp_batch`
                LEFT JOIN (
                    SELECT
                        `pickware_erp_batch_stock_mapping`.`batch_id` AS `batch_id`,
                        SUM(`pickware_erp_batch_stock_mapping`.`quantity`) AS `physical_stock`
                    FROM `pickware_erp_batch_stock_mapping`
                    JOIN `pickware_erp_stock`
                        ON `pickware_erp_stock`.`id` = `pickware_erp_batch_stock_mapping`.`stock_id`
                    WHERE
                        `pickware_erp_stock`.`product_id` IN (:productIds)
                        AND `pickware_erp_stock`.`location_type_technical_name` IN (:relevantLocationTypes)
                    GROUP BY `pickware_erp_batch_stock_mapping`.`batch_id`
                ) AS `batch_quantities`
                    ON `pickware_erp_batch`.`id` = `batch_quantities`.`batch_id`
                SET `pickware_erp_batch`.`physical_stock` = COALESCE(`batch_quantities`.`physical_stock`, 0)
                WHERE
                    `pickware_erp_batch`.`product_id` IN (:productIds)
                    AND `pickware_erp_batch`.`product_version_id` = :liveVersionId;
                SQL,
            [
                'productIds' => array_map('hex2bin', $productIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'relevantLocationTypes' => LocationTypeDefinition::TECHNICAL_NAMES_INTERNAL,
            ],
            [
                'productIds' => ArrayParameterType::BINARY,
                'liveVersionId' => ParameterType::BINARY,
                'relevantLocationTypes' => ArrayParameterType::STRING,
            ],
        );
    }
}
