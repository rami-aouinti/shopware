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
use Doctrine\DBAL\ParameterType;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailLoggingService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductAvailableStockUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $db,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PaperTrailLoggingService $paperTrailLoggingService,
    ) {}

    /**
     * available stock = product stock - reserved stock - stock not available for sale
     *
     * If any of the 3 stock values on the right changes, we need to recalculate the available stock.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            StockUpdatedForStockMovementsEvent::class => 'productStockUpdated',
            ProductReservedStockUpdatedEvent::class => 'productReservedStockUpdated',
            StockNotAvailableForSaleUpdatedEvent::class => 'stockNotAvailableForSaleUpdated',
            StockNotAvailableForSaleUpdatedForAllProductsInWarehousesEvent::class => 'stockNotAvailableForSaleUpdatedForAllProductsInWarehouses',
        ];
    }

    /**
     * Feature detection to determine the new available stock behavior in erp in other plugins.
     * See https://github.com/pickware/shopware-plugins/issues/7479
     * @deprecated Will be removed with 5.0.0
     */
    public function doesWriteAvailableStockToProductStock(): bool
    {
        return true;
    }

    public function productStockUpdated(StockUpdatedForStockMovementsEvent $event): void
    {
        $productIds = array_values(array_map(
            fn(array $stockMovement) => $stockMovement['productId'],
            $event->getStockMovements(),
        ));

        $this->recalculateProductAvailableStock($productIds, $event->getContext());
    }

    public function productReservedStockUpdated(ProductReservedStockUpdatedEvent $event): void
    {
        $this->recalculateProductAvailableStock($event->getProductIds(), $event->getContext());
    }

    public function stockNotAvailableForSaleUpdated(StockNotAvailableForSaleUpdatedEvent $event): void
    {
        $this->recalculateProductAvailableStock($event->getProductIds(), $event->getContext());
    }

    public function stockNotAvailableForSaleUpdatedForAllProductsInWarehouses(StockNotAvailableForSaleUpdatedForAllProductsInWarehousesEvent $event): void
    {
        if (count($event->getWarehouseIds()) === 0) {
            return;
        }

        $this->db->executeStatement(
            <<<SQL
                UPDATE `pickware_erp_warehouse_stock` warehouseStock
                INNER JOIN `product`
                    ON `product`.`id` = warehouseStock.`product_id`
                    AND `product`.`version_id` = warehouseStock.`product_version_id`
                SET
                    product.`available_stock` = product.`available_stock` + (
                        IF(:warehouseStockIsNowNotAvailableForSale, -1, 1) * warehouseStock.`quantity`
                    ),
                    product.`stock` = product.`stock` + (
                        IF(:warehouseStockIsNowNotAvailableForSale, -1, 1) * warehouseStock.`quantity`
                    )
                WHERE
                    warehouseStock.`warehouse_id` IN (:warehouseIds)
                    AND warehouseStock.`quantity` > 0
                    AND `product`.`version_id` = :liveVersionId;
                SQL,
            [
                'warehouseIds' => Uuid::fromHexToBytesList($event->getWarehouseIds()),
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'warehouseStockIsNowNotAvailableForSale' => $event->isWarehouseStockNowNotAvailableForSale(),
            ],
            [
                'warehouseIds' => ArrayParameterType::BINARY,
                'liveVersionId' => ParameterType::BINARY,
                'warehouseStockIsNowNotAvailableForSale' => ParameterType::BOOLEAN,
            ],
        );

        $availableStockChanges = $this->db->fetchAllAssociative(
            <<<SQL
                SELECT
                    LOWER(HEX(`product`.`id`)) AS `id`,
                    `product`.`available_stock` AS `newAvailableStock`
                FROM
                    `product`
                INNER JOIN `pickware_erp_warehouse_stock`
                    ON `product`.`id` = `pickware_erp_warehouse_stock`.`product_id`
                    AND `product`.`version_id` = `pickware_erp_warehouse_stock`.`product_version_id`
                WHERE
                    `product`.`version_id` = :liveVersionId
                    AND `pickware_erp_warehouse_stock`.`warehouse_id` IN (:warehouseIds)
                SQL,
            [
                'warehouseIds' => Uuid::fromHexToBytesList($event->getWarehouseIds()),
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'warehouseIds' => ArrayParameterType::BINARY,
                'liveVersionId' => ParameterType::BINARY,
            ],
        );
        $this->paperTrailLoggingService->logPaperTrailEvent(
            'Available stock updated for products',
            [
                'calculation' => 'from-warehouse-stock',
                'changes' => $availableStockChanges,
            ],
        );
        $this->eventDispatcher->dispatch(new ProductAvailableStockUpdatedEvent(
            array_column($availableStockChanges, 'id'),
            $event->getContext(),
        ));
    }

    /**
     * @param list<string> $productIds
     */
    public function recalculateProductAvailableStock(array $productIds, Context $context): void
    {
        if (count($productIds) === 0) {
            return;
        }

        RetryableTransaction::retryable($this->db, function() use ($productIds): void {
            $this->db->executeStatement(
                <<<SQL
                    UPDATE `product`
                    LEFT JOIN `pickware_erp_pickware_product` `pickwareProduct`
                        ON pickwareProduct.`product_id` = `product`.`id`
                        AND pickwareProduct.`product_version_id` = `product`.`version_id`
                    # The available stock can be negative
                    SET
                        `product`.`available_stock` = (
                            `pickwareProduct`.`physical_stock`
                            - `pickwareProduct`.`stock_not_available_for_sale`
                            - `pickwareProduct`.`reserved_stock`
                        ),
                        `product`.`stock` = (
                            `pickwareProduct`.`physical_stock`
                            - `pickwareProduct`.`stock_not_available_for_sale`
                            - `pickwareProduct`.`reserved_stock`
                        )
                    WHERE
                        `product`.`version_id` = :liveVersionId
                        AND `product`.`id` IN (:productIds)
                    SQL,
                [
                    'productIds' => Uuid::fromHexToBytesList($productIds),
                    'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                ],
                [
                    'productIds' => ArrayParameterType::BINARY,
                    'liveVersionId' => ParameterType::BINARY,
                ],
            );
        });

        $availableStockChanges = $this->db->fetchAllAssociative(
            <<<SQL
                SELECT
                    LOWER(HEX(`product`.`id`)) AS `id`,
                    `product`.`available_stock` AS `newAvailableStock`
                FROM
                    `product`
                WHERE
                    `product`.`id` IN (:productIds)
                    AND `product`.`version_id` = :liveVersionId
                SQL,
            [
                'productIds' => Uuid::fromHexToBytesList($productIds),
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'productIds' => ArrayParameterType::BINARY,
                'liveVersionId' => ParameterType::BINARY,
            ],
        );
        $this->paperTrailLoggingService->logPaperTrailEvent(
            'Available stock updated for products',
            [
                'calculation' => 'from-pickware-product',
                'changes' => $availableStockChanges,
            ],
        );
        $this->eventDispatcher->dispatch(new ProductAvailableStockUpdatedEvent($productIds, $context));
    }
}
