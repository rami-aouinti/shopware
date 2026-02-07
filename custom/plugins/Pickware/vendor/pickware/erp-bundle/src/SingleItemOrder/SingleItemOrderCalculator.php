<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SingleItemOrder;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Pickware\DalBundle\IdResolver\CachedStateIdService;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class SingleItemOrderCalculator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CachedStateIdService $cachedStateIdService,
    ) {}

    /**
     * @param string[] $orderIds
     * @return string[]
     */
    public function calculateSingleItemOrdersForOrderIds(array $orderIds): array
    {
        if (count($orderIds) === 0) {
            return [];
        }

        return $this->connection->fetchFirstColumn(
            <<<SQL
                WITH `eligible_order_ids` AS (
                    SELECT `order`.`id`
                    FROM `order`
                    WHERE `order`.`version_id` = :liveVersionId
                        AND `order`.`id` IN (:orderIds)
                ),
                `eligible_order_line_items` AS (
                    SELECT `order_line_item`.`order_id`, `order_line_item`.`quantity`
                    FROM `order_line_item`
                    WHERE `order_line_item`.`order_id` IN (SELECT `eligible_order_ids`.`id` FROM `eligible_order_ids`)
                        AND `order_line_item`.`order_version_id` = :liveVersionId
                        AND `order_line_item`.`type` = :orderLineItemTypeProduct
                        AND `order_line_item`.`parent_id` IS NULL
                )
                SELECT LOWER(HEX(`eligible_order_line_items`.`order_id`))
                FROM `eligible_order_line_items`
                GROUP BY `eligible_order_line_items`.`order_id`
                HAVING COUNT(*) = 1
                    AND SUM(`eligible_order_line_items`.`quantity`) = 1;
                SQL,
            [
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'orderIds' => Uuid::fromHexToBytesList($orderIds),
                'orderLineItemTypeProduct' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            ],
            [
                'liveVersionId' => ParameterType::BINARY,
                'orderIds' => ArrayParameterType::BINARY,
            ],
        );
    }

    /**
     * @return string[]
     */
    public function getAllOpenSingleItemOrderIds(): array
    {
        // This query is optimized for performance with the main goal to scan the least number of `order_line_item`
        // rows. The problem is mainly a missing index on the `type` column, but also other indexes are missing.
        // The CTEs help MySQL reduce the number of order ids *before* touching any `order_line_item` rows. Writing
        // the query differently could result in the optimizer filtering `order_line_item` first, which would cause a
        // full table scan on that table.
        return $this->connection->fetchFirstColumn(
            <<<SQL
                WITH `eligible_orders` AS (
                    SELECT `order`.`id`
                    FROM `order`
                    WHERE `order`.`version_id` = :liveVersionId
                        AND `order`.`state_id` NOT IN (:excludedOrderStateIds)
                ),
                `orders_with_eligible_deliveries` AS (
                    SELECT `order_delivery`.`order_id`
                    FROM `order_delivery`
                    INNER JOIN `eligible_orders`
                        ON `eligible_orders`.`id` = `order_delivery`.`order_id`
                        AND `order_delivery`.`version_id` = :liveVersionId
                    WHERE `order_delivery`.`state_id` NOT IN (:excludedOrderDeliveryStateIds)
                    -- GROUP BY here is faster than DISTINCT
                    GROUP BY `order_delivery`.`order_id`
                ),
                `order_without_deliveries` AS (
                    SELECT `eligible_orders`.`id`
                    FROM `eligible_orders`
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM `order_delivery`
                        WHERE `order_delivery`.`order_id` = `eligible_orders`.`id`
                            AND `order_delivery`.`version_id` = :liveVersionId
                    )
                ),
                `final_order_ids` AS (
                    SELECT `orders_with_eligible_deliveries`.`order_id` FROM `orders_with_eligible_deliveries`
                    UNION ALL
                    SELECT `order_without_deliveries`.`id` FROM `order_without_deliveries`
                )
                SELECT LOWER(HEX(`final_order_ids`.`order_id`))
                FROM `final_order_ids`
                INNER JOIN `order_line_item`
                    ON `order_line_item`.`order_id` = `final_order_ids`.`order_id`
                    AND `order_line_item`.`order_version_id` = :liveVersionId
                WHERE `order_line_item`.`type` = :orderLineItemTypeProduct
                    AND `order_line_item`.`parent_id` IS NULL
                GROUP BY `final_order_ids`.`order_id`
                HAVING COUNT(*) = 1
                    AND SUM(`order_line_item`.`quantity`) = 1
                SQL,
            [
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'excludedOrderStateIds' => Uuid::fromHexToBytesList($this->cachedStateIdService->getOrderStateIds([
                    OrderStates::STATE_COMPLETED,
                    OrderStates::STATE_CANCELLED,
                ])),
                'excludedOrderDeliveryStateIds' => Uuid::fromHexToBytesList($this->cachedStateIdService->getOrderDeliveryStateIds([
                    OrderDeliveryStates::STATE_SHIPPED,
                    OrderDeliveryStates::STATE_CANCELLED,
                ])),
                'orderLineItemTypeProduct' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            ],
            [
                'liveVersionId' => ParameterType::BINARY,
                'excludedOrderStateIds' => ArrayParameterType::BINARY,
                'excludedOrderDeliveryStateIds' => ArrayParameterType::BINARY,
            ],
        );
    }
}
