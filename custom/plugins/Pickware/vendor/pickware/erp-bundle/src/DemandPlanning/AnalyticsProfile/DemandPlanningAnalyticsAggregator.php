<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemandPlanning\AnalyticsProfile;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\PickwareErpStarter\Analytics\AnalyticsAggregator;
use Pickware\PickwareErpStarter\Analytics\DependencyInjection\AnalyticsAggregatorConfigFactoryRegistry;
use Pickware\PickwareErpStarter\Analytics\Model\AnalyticsAggregationSessionDefinition;
use Pickware\PickwareErpStarter\Analytics\Model\AnalyticsAggregationSessionEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;

class DemandPlanningAnalyticsAggregator implements AnalyticsAggregator
{
    public const ITEM_TABLE_NAME = 'pickware_erp_analytics_aggregation_item_demand_planning';
    private const ORDER_STATE_EXCLUDE_LIST = [OrderStates::STATE_CANCELLED];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly AnalyticsAggregatorConfigFactoryRegistry $aggregatorConfigFactoryRegistry,
        private readonly Connection $connection,
    ) {}

    public function aggregate(string $aggregationSessionId, Context $context): void
    {
        /** @var AnalyticsAggregationSessionEntity $aggregationSession */
        $aggregationSession = $this->entityManager->getByPrimaryKey(
            AnalyticsAggregationSessionDefinition::class,
            $aggregationSessionId,
            $context,
        );

        /** @var DemandPlanningAnalyticsAggregatorConfig $config */
        $config = $this->aggregatorConfigFactoryRegistry
            ->getAnalyticsAggregatorConfigFactoryByAggregationTechnicalName($this->getAggregationTechnicalName())
            ->createAggregatorConfigFromArray($aggregationSession->getConfig());

        $additionalFilter = '';
        if ($config->showOnlyStockAtOrBelowReorderPoint) {
            $additionalFilter .= 'AND `pickwareProduct`.`stock_below_reorder_point` >= 0';
        }

        // Create demand planning list items with new configuration
        $salesCalculation = 'IFNULL(`orderLineItemProductsInSalesInterval`.`quantity`, 0)';
        $salesPredictionCalculation = 'CEIL(' . $salesCalculation . ' * :referenceSalesToPredictionFactor)';
        $reservedStockCalculation = 'IFNULL(`pickwareProduct`.`reserved_stock`, 0)';
        $incomingStock = 'IFNULL(`pickwareProduct`.`incoming_stock`, 0)';
        $purchaseSuggestionCalculation = 'GREATEST(
            0,
            (`pickwareProduct`.`reorder_point` + (' . $reservedStockCalculation . ' * :considerOpenOrdersInPurchaseSuggestion) - `pickwareProduct`.`physical_stock` - ' . $incomingStock . '),
            (' . $salesPredictionCalculation . ' + (' . $reservedStockCalculation . ' * :considerOpenOrdersInPurchaseSuggestion) - `pickwareProduct`.`physical_stock` - ' . $incomingStock . ')
        )';

        $this->connection->executeStatement(
            'INSERT INTO `pickware_erp_analytics_aggregation_item_demand_planning` (
                `id`,
                `aggregation_session_id`,
                `product_id`,
                `product_version_id`,
                `sales`,
                `sales_prediction`,
                `reserved_stock`,
                `available_stock`,
                `stock`,
                `reorder_point`,
                `incoming_stock`,
                `purchase_suggestion`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                :sessionId,
                `product`.`id`,
                `product`.`version_id`,
                ' . $salesCalculation . ',
                ' . $salesPredictionCalculation . ',
                ' . $reservedStockCalculation . ',
                `product`.`available_stock`,
                `pickwareProduct`.`physical_stock`,
                `pickwareProduct`.`reorder_point`,
                ' . $incomingStock . ',
                ' . $purchaseSuggestionCalculation . '
            FROM `product`
            LEFT JOIN `pickware_erp_pickware_product` AS `pickwareProduct`
                ON `pickwareProduct`.`product_id` = `product`.`id`
                AND `pickwareProduct`.`product_version_id` = `product`.`version_id`
            LEFT JOIN (
                SELECT
                    SUM(`order_line_item`.`quantity`) AS `quantity`,
                    `order_line_item`.`product_id`
                FROM `order_line_item`
                INNER JOIN `order`
                    ON `order`.`id` = `order_line_item`.`order_id`
                    AND `order`.`version_id` = `order_line_item`.`order_version_id`
                    AND `order`.`order_date` >= :fromDate
                    AND `order`.`order_date` <= :toDate
                    AND `order`.`version_id` = :liveVersionId
                INNER JOIN `state_machine_state` AS `orderState`
                    ON `order`.`state_id` = `orderState`.`id`
                    AND `orderState`.`technical_name` NOT IN (:orderStateExcludeList)
                GROUP BY `order_line_item`.`product_id`
            ) AS `orderLineItemProductsInSalesInterval`
                ON `orderLineItemProductsInSalesInterval`.`product_id` = `product`.`id`
            WHERE `product`.`version_id` = :liveVersionId
            ' . $additionalFilter,
            [
                'sessionId' => hex2bin($aggregationSessionId),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'fromDate' => $config->salesReferenceIntervalFromDate->format('Y-m-d'),
                'toDate' => $config->salesReferenceIntervalToDate->format('Y-m-d'),
                'referenceSalesToPredictionFactor' => $config->getReferenceSalesToPredictionFactor(),
                'considerOpenOrdersInPurchaseSuggestion' => $config->considerOpenOrdersInPurchaseSuggestion ? 1 : 0,
                'orderStateExcludeList' => self::ORDER_STATE_EXCLUDE_LIST,
            ],
            [
                'orderStateExcludeList' => ArrayParameterType::STRING,
            ],
        );
    }

    public function getAggregationTechnicalName(): string
    {
        return DemandPlanningAnalyticsAggregation::TECHNICAL_NAME;
    }

    public function getAggregationItemsTableName(): string
    {
        return self::ITEM_TABLE_NAME;
    }
}
