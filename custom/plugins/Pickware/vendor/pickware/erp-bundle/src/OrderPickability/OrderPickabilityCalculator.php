<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderPickability;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Pickware\DalBundle\IdResolver\CachedStateIdService;
use Pickware\PhpStandardLibrary\Collection\Map;
use Pickware\PickwareErpStarter\Database\MariaDBOptimizerDisabler;
use Pickware\PickwareErpStarter\OrderPickability\Model\OrderPickabilityCollection;
use Pickware\PickwareErpStarter\OrderPickability\Model\OrderPickabilityDefinition;
use Pickware\PickwareErpStarter\OrderPickability\Model\OrderPickabilityEntity;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @phpstan-import-type OrderPickabilityStatus from OrderPickabilityDefinition
 */
class OrderPickabilityCalculator implements OrderPickabilityCalculatorInterface
{
    // For orders with any state in the ignore list, no pickability will be calculated. This way we avoid performing
    // expensive calculations for old (completed/canceled/..) orders.
    private const ORDER_STATE_IGNORE_LIST = [
        OrderStates::STATE_CANCELLED,
        OrderStates::STATE_COMPLETED,
    ];
    private const ORDER_DELIVERY_STATE_IGNORE_LIST = [
        OrderDeliveryStates::STATE_CANCELLED,
        OrderDeliveryStates::STATE_SHIPPED,
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MariaDBOptimizerDisabler $mariaDBOptimizerDisabler,
        private readonly CachedStateIdService $cachedStateIdService,
    ) {}

    /**
     * @param string[] $orderIds
     */
    public function calculateOrderPickabilitiesForOrders(array $orderIds): OrderPickabilityCollection
    {
        if (count($orderIds) === 0) {
            return new OrderPickabilityCollection();
        }

        $allWarehouseIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(`id`)) FROM `pickware_erp_warehouse`;',
        );

        return $this->calculateOrderPickabilitiesForOrdersAndWarehouses(
            $this->getFilteredOrderIds($orderIds),
            $allWarehouseIds,
        );
    }

    /**
     * @param string[] $warehouseIds
     */
    public function calculateOrderPickabilitiesForWarehouses(array $warehouseIds): OrderPickabilityCollection
    {
        if (count($warehouseIds) === 0) {
            return new OrderPickabilityCollection();
        }

        return $this->calculateOrderPickabilitiesForOrdersAndWarehouses(
            $this->getFilteredOrderIds(),
            $warehouseIds,
        );
    }

    /**
     * @return Map<string, array{status: OrderPickabilityStatus, availableStock: int, requiredStock: int}>
     */
    public function calculateProductPickabilitiesForOrderAndWarehouse(string $orderId, string $warehouseId): Map
    {
        $orderLineItemPickabilityQuery = $this->getPickabilityForProductsInOrderQuery();

        $results = $this->connection->fetchAllAssociative(
            <<<SQL
                SELECT
                    LOWER(HEX(`orderLineItemPickability`.`product_id`)) AS `productId`,
                    CASE
                        WHEN `orderLineItemPickability`.`order_id` IS NULL
                            THEN NULL
                        WHEN IFNULL(`orderLineItemPickability`.`completely_pickable`, 0)
                            THEN :pickabilityStatusCompletelyPickable
                        WHEN IFNULL(`orderLineItemPickability`.`partially_pickable`, 0) > 0
                            THEN :pickabilityStatusPartiallyPickable
                        ELSE :pickabilityStatusNotPickable
                    END AS `orderLineItemPickabilityStatus`,
                    `orderLineItemPickability`.`available_stock` AS `availableStock`,
                    `orderLineItemPickability`.`required_stock` AS `requiredStock`
                FROM (
                    {$orderLineItemPickabilityQuery}
                ) AS `orderLineItemPickability`
                WHERE `orderLineItemPickability`.order_id = :orderId
                    AND `orderLineItemPickability`.order_version_id = :liveVersionId
                SQL,
            [
                'orderId' => Uuid::fromHexToBytes($orderId),
                'orderIds' => [Uuid::fromHexToBytes($orderId)],
                'warehouseIds' => [Uuid::fromHexToBytes($warehouseId)],
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'pickabilityStatusCompletelyPickable' => OrderPickabilityDefinition::PICKABILITY_STATUS_COMPLETELY_PICKABLE,
                'pickabilityStatusPartiallyPickable' => OrderPickabilityDefinition::PICKABILITY_STATUS_PARTIALLY_PICKABLE,
                'pickabilityStatusNotPickable' => OrderPickabilityDefinition::PICKABILITY_STATUS_NOT_PICKABLE,
                'orderLineItemTypeProduct' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            ],
            [
                'orderId' => ParameterType::BINARY,
                'orderIds' => ArrayParameterType::BINARY,
                'warehouseIds' => ArrayParameterType::BINARY,
                'liveVersionId' => ParameterType::BINARY,
            ],
        );

        $result = new Map();
        foreach ($results as $row) {
            $result->set($row['productId'], [
                'status' => $row['orderLineItemPickabilityStatus'],
                'availableStock' => (int) $row['availableStock'],
                'requiredStock' => (int) $row['requiredStock'],
            ]);
        }

        return $result;
    }

    /**
     * @return string[]
     */
    public function getOrderIdsWithoutPickabilities(): array
    {
        // For performance reasons we pre-fetch the ignored state IDs instead of adding another join when filtering
        $ignoredOrderStateIds = $this->cachedStateIdService->getOrderStateIds(self::ORDER_STATE_IGNORE_LIST);
        $ignoredOrderDeliveryStateIds = $this->cachedStateIdService->getOrderDeliveryStateIds(self::ORDER_DELIVERY_STATE_IGNORE_LIST);

        $filterQuery = <<<SQL
            SELECT DISTINCT
                LOWER(HEX(`order`.`id`))
            FROM `order`

            LEFT JOIN (
                SELECT
                    `order_delivery`.`order_id`,
                    `order_delivery`.`order_version_id`,
                    `order_delivery`.`id`,
                    -- Rank order delivery with the highest shippingCosts.unitPrice as the primary order delivery for
                    -- the order. This selection strategy is adapted from how order deliveries are selected in the
                    -- administration. See /administration/src/module/sw-order/view/sw-order-detail-base/index.js
                    ROW_NUMBER() OVER (
                        PARTITION BY
                            `order_delivery`.`order_id`,
                            `order_delivery`.`order_version_id`
                        ORDER BY CAST(JSON_UNQUOTE(JSON_EXTRACT(`order_delivery`.`shipping_costs`, "$.unitPrice")) AS DECIMAL(10, 2)) DESC, `order_delivery`.`id`
                    ) as `rank`
                FROM `order_delivery`
                INNER JOIN `order` `od_order` ON
                    `order_delivery`.order_id = `od_order`.id
                    AND `order_delivery`.order_version_id = `od_order`.version_id
                    AND `od_order`.`version_id` = :liveVersionId
                    AND `od_order`.`state_id` NOT IN (:ignoredOrderStateIds)
                WHERE
                    `order_delivery`.`state_id` NOT IN (:ignoredOrderDeliveryStateIds)
            ) AS `primary_delivery_ranked` ON
                `primary_delivery_ranked`.`order_id` = `order`.`id`
                AND `primary_delivery_ranked`.`order_version_id` = `order`.`version_id`
                -- Selects the primary order delivery (rank 1)
                AND `primary_delivery_ranked`.`rank` = 1

            WHERE
                `order`.`version_id` = :liveVersionId
                AND (
                    `order`.`state_id` IN (:ignoredOrderStateIds)
                    OR `primary_delivery_ranked`.`id` IS NULL
                )
            SQL;

        $orderIds = $this->connection->fetchFirstColumn(
            $filterQuery,
            [
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'ignoredOrderStateIds' => array_map('hex2bin', $ignoredOrderStateIds),
                'ignoredOrderDeliveryStateIds' => array_map('hex2bin', $ignoredOrderDeliveryStateIds),
            ],
            [
                'ignoredOrderStateIds' => ArrayParameterType::BINARY,
                'ignoredOrderDeliveryStateIds' => ArrayParameterType::BINARY,
            ],
        );

        $event = new CollectAdditionalOrdersWithoutPickabilityEvent();
        $this->eventDispatcher->dispatch($event);

        return array_merge($orderIds, $event->getOrdersWithoutPickability());
    }

    /**
     * @param string[] $orderIds
     * @param string[] $warehouseIds
     */
    private function calculateOrderPickabilitiesForOrdersAndWarehouses(
        array $orderIds,
        array $warehouseIds,
    ): OrderPickabilityCollection {
        if (count($orderIds) === 0 || count($warehouseIds) === 0) {
            return new OrderPickabilityCollection();
        }

        $orderLineItemPickabilityQuery = $this->getPickabilityForProductsInOrderQuery();

        $pickabilityQuery = <<<SQL
            SELECT
                LOWER(HEX(`order`.`id`)) AS `orderId`,
                LOWER(HEX(`order`.`version_id`)) AS `orderVersionId`,
                LOWER(HEX(`order_line_item_pickability`.`warehouse_id`)) AS `warehouseId`,
                CASE
                    -- If no order line items have a pickability, the order has no pickability
                    WHEN SUM(`order_line_item_pickability`.`order_id` IS NULL) = COUNT(*)
                        THEN NULL

                    -- If all order line items are completely pickable, the order is "completely pickable"
                    WHEN SUM(IFNULL(`order_line_item_pickability`.`completely_pickable`, 0)) = COUNT(*)
                        THEN :pickabilityStatusCompletelyPickable

                    -- If at least one order line item is partially pickable (which is also true for order line items
                    -- that are completely pickable), the order is "partially pickable"
                    WHEN SUM(IFNULL(`order_line_item_pickability`.`partially_pickable`, 0)) > 0
                        THEN :pickabilityStatusPartiallyPickable

                    -- Otherwise (not a single order line item is not even partially pickable) the order is
                    -- "not pickable"
                    ELSE :pickabilityStatusNotPickable
                END AS `orderPickabilityStatus`,
                UTC_TIMESTAMP(3) AS `createdAt`
            FROM `order`
            LEFT JOIN (
                {$orderLineItemPickabilityQuery}
            ) AS `order_line_item_pickability`
                ON `order_line_item_pickability`.`order_id` = `order`.`id`
                AND `order_line_item_pickability`.`order_version_id` = `order`.`version_id`
            WHERE
                `order`.`id` IN (:orderIds)
                AND `order`.`version_id` = :liveVersionId
            GROUP BY
                `order`.`id`,
                `order`.`version_id`,
                `order_line_item_pickability`.`warehouse_id`
            SQL;

        $rawPickabilities = $this->mariaDBOptimizerDisabler->runWithDisabledQueryOptimizationIfConfigured(
            fn() => $this->connection->fetchAllAssociative(
                $pickabilityQuery,
                [
                    'pickabilityStatusCompletelyPickable' => OrderPickabilityDefinition::PICKABILITY_STATUS_COMPLETELY_PICKABLE,
                    'pickabilityStatusPartiallyPickable' => OrderPickabilityDefinition::PICKABILITY_STATUS_PARTIALLY_PICKABLE,
                    'pickabilityStatusNotPickable' => OrderPickabilityDefinition::PICKABILITY_STATUS_NOT_PICKABLE,
                    'orderLineItemTypeProduct' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                    'orderIds' => array_map('hex2bin', $orderIds),
                    'warehouseIds' => array_map('hex2bin', $warehouseIds),
                ],
                [
                    'orderIds' => ArrayParameterType::BINARY,
                    'warehouseIds' => ArrayParameterType::BINARY,
                ],
            ),
        );

        // Add any missing entries for order/warehouse combinations that cannot yield a result in the SQL query because
        // e.g. the order only contains line items of other types than "product" and hence no row in
        // `order_line_item_pickability` can be joined
        $pickabilitiesByOrderId = [];
        foreach ($rawPickabilities as $pickability) {
            $orderId = $pickability['orderId'];
            $pickabilitiesByOrderId[$orderId] ??= [];
            $pickabilitiesByOrderId[$orderId][] = $pickability;
        }
        $orderPickabilityCollection = new OrderPickabilityCollection();
        foreach ($pickabilitiesByOrderId as $orderId => $orderPickabilities) {
            foreach ($warehouseIds as $warehouseId) {
                $warehousePickability = current(array_filter(
                    $orderPickabilities,
                    fn(array $pickability) => $pickability['warehouseId'] === $warehouseId,
                ));
                if ($warehousePickability) {
                    $pickabilityEntity = new OrderPickabilityEntity();
                    $pickabilityEntity->setId(Uuid::randomHex());
                    $pickabilityEntity->assign($warehousePickability);
                    $orderPickabilityCollection->add($pickabilityEntity);
                }
            }
        }

        return $orderPickabilityCollection;
    }

    private function getPickabilityForProductsInOrderQuery(): string
    {
        /** @var OrderPickabilityQueryExtensionEvent $queryExtensionEvent */
        $queryExtensionEvent = $this->eventDispatcher->dispatch(new OrderPickabilityQueryExtensionEvent());

        $availableWarehouseStockQuery = '`warehouse_stock`.`quantity`';
        $reservedWarehouseStockJoinQuery = '';
        if ($queryExtensionEvent->getReservedWarehouseStockQuery() !== null) {
            $availableWarehouseStockQuery = '(`warehouse_stock`.`quantity` - IFNULL(`reserved_warehouse_stock`.`quantity`, 0))';
            $reservedWarehouseStockQuery = $queryExtensionEvent->getReservedWarehouseStockQuery();
            $reservedWarehouseStockJoinQuery = <<<SQL
                LEFT JOIN
                    {$reservedWarehouseStockQuery}
                AS `reserved_warehouse_stock`
                    ON `reserved_warehouse_stock`.`warehouse_id` = `warehouse_stock`.`warehouse_id`
                    AND `reserved_warehouse_stock`.`product_id` = `warehouse_stock`.`product_id`
                    AND `reserved_warehouse_stock`.`product_version_id` = `warehouse_stock`.`product_version_id`
                SQL;
        }
        $orderLineItemQuantityToFulfill = 'GREATEST(0, `order_line_item`.`quantity` - IFNULL(`pickware_order_line_item`.`externally_fulfilled_quantity`, 0))';

        return <<<SQL
            -- This sub select calculates the pickability of each product per order.
            SELECT
                `order_line_item`.`product_id` AS `product_id`,
                `order_line_item`.`order_id` AS `order_id`,
                `order_line_item`.`order_version_id` AS `order_version_id`,
                `warehouse_stock`.`warehouse_id` AS `warehouse_id`,

                -- "completely pickable" if there is enough stock in the warehouse for the product in the order to
                -- fulfill the quantity of the order (minus what is already stocked into the order or should be returned). This is also
                -- true if the order line item references a product for which stock management is disabled.
                (
                    `pickware_product`.`is_stock_management_disabled` = 1
                    OR MAX( {$availableWarehouseStockQuery} ) >= GREATEST(
                        0,
                        SUM({$orderLineItemQuantityToFulfill}) - IFNULL(MAX(`stock_in_order_by_product`.`quantity`), 0) - IFNULL(SUM(`return_order_line_item`.`quantity`), 0)
                    )
                ) AS `completely_pickable`,

                -- "partially pickable" if there is at least some stock in the warehouse to pick from
                (
                    `pickware_product`.`is_stock_management_disabled` = 1
                    OR (
                        `pickware_product`.`is_stock_management_disabled` = 0
                        AND {$availableWarehouseStockQuery} > 0
                    )
                ) AS `partially_pickable`,

                -- Breakdown values for pickability calculation
                MAX( {$availableWarehouseStockQuery} ) AS `available_stock`,
                GREATEST(
                    0,
                    SUM({$orderLineItemQuantityToFulfill}) - IFNULL(MAX(`stock_in_order_by_product`.`quantity`), 0) - IFNULL(SUM(`return_order_line_item`.`quantity`), 0)
                ) AS `required_stock`
            FROM `order_line_item`
            LEFT JOIN `pickware_erp_pickware_order_line_item` AS `pickware_order_line_item`
                ON `pickware_order_line_item`.`order_line_item_id` = `order_line_item`.`id`
                AND `pickware_order_line_item`.`order_line_item_version_id` = `order_line_item`.`version_id`
            INNER JOIN `pickware_erp_warehouse_stock` AS `warehouse_stock`
                ON `warehouse_stock`.`warehouse_id` IN (:warehouseIds)
                AND `warehouse_stock`.`product_id` = `order_line_item`.`product_id`
                -- Join via liveVersionId (which we know is true because of the WHERE statement below), because
                -- there is no index on the `order_line_item`.`product_version_id`, which makes this query slow
                AND `warehouse_stock`.`product_version_id` = :liveVersionId
            INNER JOIN `pickware_erp_pickware_product` AS `pickware_product`
                ON `pickware_product`.`product_id` = `order_line_item`.`product_id`
                -- Join via liveVersionId (which we know is true because of the WHERE statement below), because
                -- there is no index on the `order_line_item`.`product_version_id`, which makes this query slow
                AND `pickware_product`.`product_version_id` = :liveVersionId
            LEFT JOIN `pickware_erp_stock` AS `stock_in_order_by_product`
                ON `stock_in_order_by_product`.`order_id` = `order_line_item`.`order_id`
                AND `stock_in_order_by_product`.`order_version_id` = `order_line_item`.`order_version_id`
                AND `stock_in_order_by_product`.`product_id` = `order_line_item`.`product_id`
                -- Join via liveVersionId (which we know is true because of the WHERE statement below), because
                -- there is no index on the `order_line_item`.`product_version_id`, which makes this query slow
                AND `stock_in_order_by_product`.`product_version_id` = :liveVersionId
            LEFT JOIN `pickware_erp_return_order_line_item` AS `return_order_line_item`
                ON `return_order_line_item`.`order_line_item_id` = `order_line_item`.`id`
                AND `return_order_line_item`.`order_line_item_version_id` = `order_line_item`.`version_id`
            {$reservedWarehouseStockJoinQuery}
            WHERE
                `order_line_item`.`order_id` IN (:orderIds)
                AND `order_line_item`.`order_version_id` = :liveVersionId
                AND `order_line_item`.`version_id` = :liveVersionId
                AND `order_line_item`.`type` = :orderLineItemTypeProduct
                AND {$orderLineItemQuantityToFulfill} > 0
            GROUP BY
                `order_line_item`.`order_id`,
                `order_line_item`.`order_version_id`,
                `order_line_item`.`product_id`,
                `order_line_item`.`product_version_id`,
                `warehouse_stock`.`warehouse_id`
            HAVING
                -- Note that multiple line items of an order can reference the same product, whereas one product
                -- can only have a single stock value in each order. We still need to aggregate the latter to make
                -- the grouping work.
                SUM({$orderLineItemQuantityToFulfill}) > IFNULL(MAX(`stock_in_order_by_product`.`quantity`), 0) + IFNULL(SUM(`return_order_line_item`.`quantity`), 0)
            SQL;
    }

    /**
     * @param string[]|null $orderIds
     * @return string[]
     */
    private function getFilteredOrderIds(?array $orderIds = null): array
    {
        // For performance reasons we pre-fetch the ignored state IDs instead of adding another join when filtering
        $ignoredOrderStateIds = $this->cachedStateIdService->getOrderStateIds(self::ORDER_STATE_IGNORE_LIST);
        $ignoredOrderDeliveryStateIds = $this->cachedStateIdService->getOrderDeliveryStateIds(self::ORDER_DELIVERY_STATE_IGNORE_LIST);

        $filterQueryArguments = [
            'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            'ignoredOrderStateIds' => array_map('hex2bin', $ignoredOrderStateIds),
            'ignoredOrderDeliveryStateIds' => array_map('hex2bin', $ignoredOrderDeliveryStateIds),
        ];
        $filterQueryArgumentTypes = [
            'ignoredOrderStateIds' => ArrayParameterType::BINARY,
            'ignoredOrderDeliveryStateIds' => ArrayParameterType::BINARY,
        ];

        $orderIdFilterString = '';
        $orderDeliveryOrderIdFilterString = '';
        if (is_array($orderIds) && count($orderIds) > 0) {
            $orderIdFilterString = ' AND `order`.`id` IN (:orderIds)';
            $orderDeliveryOrderIdFilterString = ' AND `od_order`.`id` IN (:orderIds)';
            $filterQueryArguments['orderIds'] = array_map('hex2bin', $orderIds);
            $filterQueryArgumentTypes['orderIds'] = ArrayParameterType::BINARY;
        }

        $filterQuery = <<<SQL
            SELECT
                LOWER(HEX(`order`.`id`))
            FROM `order`
            INNER JOIN (
                SELECT
                    `order_delivery`.`order_id`,
                    `order_delivery`.`order_version_id`,
                    `order_delivery`.`id`,
                    -- Rank order delivery with the highest shippingCosts.unitPrice as the primary order delivery for
                    -- the order. This selection strategy is adapted from how order deliveries are selected in the
                    -- administration. See /administration/src/module/sw-order/view/sw-order-detail-base/index.js
                    ROW_NUMBER() OVER (
                        PARTITION BY
                            `order_delivery`.`order_id`,
                            `order_delivery`.`order_version_id`
                        ORDER BY CAST(JSON_UNQUOTE(JSON_EXTRACT(`order_delivery`.`shipping_costs`, "$.unitPrice")) AS DECIMAL(10, 2)) DESC, `order_delivery`.`id`
                    ) as `rank`
                FROM `order_delivery`
                INNER JOIN `order` `od_order` ON
                    `order_delivery`.order_id = `od_order`.id
                    AND `order_delivery`.order_version_id = `od_order`.version_id
                    {$orderDeliveryOrderIdFilterString}
                    AND `od_order`.`version_id` = :liveVersionId
                    AND `od_order`.`state_id` NOT IN (:ignoredOrderStateIds)
                WHERE
                    `order_delivery`.`state_id` NOT IN (:ignoredOrderDeliveryStateIds)
            ) AS `primary_delivery_ranked` ON
                `primary_delivery_ranked`.`order_id` = `order`.`id`
                AND `primary_delivery_ranked`.`order_version_id` = `order`.`version_id`
                -- Selects the primary order delivery (rank 1)
                AND `primary_delivery_ranked`.`rank` = 1
            WHERE
                `order`.`version_id` = :liveVersionId
                {$orderIdFilterString}
                AND `order`.`state_id` NOT IN (:ignoredOrderStateIds)
            SQL;

        $orderIds = $this->connection->fetchFirstColumn(
            $filterQuery,
            $filterQueryArguments,
            $filterQueryArgumentTypes,
        );

        $event = new CollectAdditionalOrdersWithoutPickabilityEvent();
        $this->eventDispatcher->dispatch($event);

        return array_diff($orderIds, $event->getOrdersWithoutPickability());
    }
}
