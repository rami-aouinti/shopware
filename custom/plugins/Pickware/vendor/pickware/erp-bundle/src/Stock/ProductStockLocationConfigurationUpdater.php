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
use Pickware\PickwareErpStarter\Stock\Model\ProductStockLocationConfigurationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductStockLocationConfigurationUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $db,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ProductStockLocationConfigurationDefinition::ENTITY_WRITTEN_EVENT => 'recalculateStockBelowReorderPointForProductStockLocationConfigurations',
        ];
    }

    /**
     * Whenever a product stock location configuration is updated, we need to recalculate the stock below reorder point,
     * as the reorder point might have changed.
     */
    public function recalculateStockBelowReorderPointForProductStockLocationConfigurations(EntityWrittenEvent $entityWrittenEvent): void
    {
        $productStockLocationConfigurationIds = array_map(fn($writeResult) => $writeResult->getPayload()['id'], $entityWrittenEvent->getWriteResults());

        $this->db->executeStatement(
            'UPDATE `pickware_erp_product_stock_location_configuration` `config`
                INNER JOIN `pickware_erp_product_stock_location_mapping` `mapping`
                    ON `mapping`.`id` = `config`.`product_stock_location_mapping_id`
                LEFT JOIN `pickware_erp_stock` `stock`
                    ON `stock`.`id` = `mapping`.`stock_id`
                SET `config`.`stock_below_reorder_point` = IF(
                        `stock`.`id` IS NULL,
                        NULL,
                        `config`.`reorder_point` - `stock`.`quantity`
                    ),
                    `config`.`updated_at` = UTC_TIMESTAMP(3)
                WHERE `config`.`id` IN (:configIds)',
            [
                'configIds' => array_map('hex2bin', $productStockLocationConfigurationIds),
            ],
            [
                'configIds' => ArrayParameterType::STRING,
            ],
        );
    }

    /**
     * @param String[] $stockIds
     */
    public function recalculateStockBelowReorderPointForStockIds(array $stockIds): void
    {
        $this->db->executeStatement(
            'UPDATE `pickware_erp_product_stock_location_configuration` `config`
                INNER JOIN `pickware_erp_product_stock_location_mapping` `mapping`
                    ON `config`.`product_stock_location_mapping_id` = `mapping`.`id`
                INNER JOIN `pickware_erp_stock` `stock`
                    ON `stock`.`id` = `mapping`.`stock_id`
                SET
                    `config`.`stock_below_reorder_point` = IF(
                        `config`.`reorder_point` IS NULL,
                        NULL,
                        `config`.`reorder_point` - `stock`.`quantity`
                    ),
                    `config`.`updated_at` = UTC_TIMESTAMP(3)
                WHERE `stock`.`id` IN (:stockIds)',
            [
                'stockIds' => array_map('hex2bin', $stockIds),
            ],
            [
                'stockIds' => ArrayParameterType::STRING,
            ],
        );
    }
}
