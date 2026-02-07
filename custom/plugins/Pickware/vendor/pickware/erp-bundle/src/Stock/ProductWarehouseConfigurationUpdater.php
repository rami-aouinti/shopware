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
use LogicException;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductWarehouseConfigurationUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $db,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ProductWarehouseConfigurationDefinition::ENTITY_WRITTEN_EVENT => 'recalculateStockBelowReorderPointForProductWarehouseConfigurations',
            WarehouseStockUpdatedEvent::EVENT_NAME => 'recalculateStockBelowReorderPointForWarehouseStock',
        ];
    }

    /**
     * Whenever a product warehouse configuration is updated, the reorder point might have changed, so we need to
     * recalculate the stock below reorder point for this configuration entry.
     *
     * This method also ensures that the warehouse stock and warehouse configuration entities are referenced to each
     * other after one is created/updated.
     */
    public function recalculateStockBelowReorderPointForProductWarehouseConfigurations(EntityWrittenEvent $entityWrittenEvent): void
    {
        $productWarehouseConfigurationIds = [];
        foreach ($entityWrittenEvent->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            $productWarehouseConfigurationIds[] = $payload['id'];
        }

        if (count($productWarehouseConfigurationIds) === 0) {
            return;
        }

        $this->db->executeStatement(
            'UPDATE `pickware_erp_product_warehouse_configuration` `productWarehouseConfiguration`
                LEFT JOIN `pickware_erp_warehouse_stock` `warehouseStock`
                    ON `warehouseStock`.`product_id` = `productWarehouseConfiguration`.`product_id`
                    AND `warehouseStock`.`product_version_id` = `productWarehouseConfiguration`.`product_version_id`
                    AND `warehouseStock`.`warehouse_id` = `productWarehouseConfiguration`.`warehouse_id`
                SET `productWarehouseConfiguration`.`updated_at` = UTC_TIMESTAMP(3),
                `productWarehouseConfiguration`.`stock_below_reorder_point` = `productWarehouseConfiguration`.`reorder_point` - `warehouseStock`.`quantity`,
                `productWarehouseConfiguration`.`warehouse_stock_id` = `warehouseStock`.`id`
                WHERE `productWarehouseConfiguration`.`id` IN (:productWarehouseConfigurationIds)
            ',
            [
                'productWarehouseConfigurationIds' => array_map('hex2bin', $productWarehouseConfigurationIds),
            ],
            [
                'productWarehouseConfigurationIds' => ArrayParameterType::STRING,
            ],
        );
    }

    /**
     * Whenever the warehouse stock changes, we need to recalculate the stock below reorder point for the product
     * warehouse configurations that are affected by this change.
     *
     * This method also ensures that the warehouse stock and warehouse configuration entities are referenced to each
     * other after one is created/updated.
     */
    public function recalculateStockBelowReorderPointForWarehouseStock(WarehouseStockUpdatedEvent $warehouseStockUpdatedEvent): void
    {
        $warehouseIds = $warehouseStockUpdatedEvent->getWarehouseIds();
        $productIds = $warehouseStockUpdatedEvent->getProductIds();

        if (count($warehouseIds) === 0 && count($productIds) === 0) {
            return;
        }
        if (count($warehouseIds) === 0 || count($productIds) === 0) {
            throw new LogicException('To recalculate the stock below reorder point, a non-empty array of product ids and warehouse ids must be provided.');
        }

        $this->db->executeStatement(
            'UPDATE `pickware_erp_product_warehouse_configuration` `productWarehouseConfiguration`
                LEFT JOIN `pickware_erp_warehouse_stock` `warehouseStock`
                    ON `warehouseStock`.`product_id` = `productWarehouseConfiguration`.`product_id`
                    AND `warehouseStock`.`product_version_id` = `productWarehouseConfiguration`.`product_version_id`
                    AND `warehouseStock`.`warehouse_id` = `productWarehouseConfiguration`.`warehouse_id`
                SET `productWarehouseConfiguration`.`updated_at` = UTC_TIMESTAMP(3),
                `productWarehouseConfiguration`.`stock_below_reorder_point` = `productWarehouseConfiguration`.`reorder_point` - `warehouseStock`.`quantity`,
                `productWarehouseConfiguration`.`warehouse_stock_id` = `warehouseStock`.`id`
                WHERE `productWarehouseConfiguration`.`warehouse_id` IN (:warehouseIds)
                AND `productWarehouseConfiguration`.`product_id` IN (:productIds)',
            [
                'warehouseIds' => array_map('hex2bin', $warehouseIds),
                'productIds' => array_map('hex2bin', $productIds),
            ],
            [
                'warehouseIds' => ArrayParameterType::STRING,
                'productIds' => ArrayParameterType::STRING,
            ],
        );
    }
}
