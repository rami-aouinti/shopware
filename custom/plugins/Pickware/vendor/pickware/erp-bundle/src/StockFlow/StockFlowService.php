<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockFlow;

use Doctrine\DBAL\Connection;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class StockFlowService
{
    public function __construct(private readonly Connection $connection) {}

    /**
     * @return StockFlow[]
     */
    public function getStockFlow(StockLocationReference $stockLocationReference): array
    {
        return $this->getCombinedStockFlow([$stockLocationReference]);
    }

    /**
     * @param StockLocationReference[] $stockLocationReferences
     */
    public function getCombinedStockFlow(array $stockLocationReferences): array
    {
        $incoming = $this->calculateCombinedStockFlow($stockLocationReferences, StockLocationReference::POSITION_DESTINATION);
        $outgoing = $this->calculateCombinedStockFlow($stockLocationReferences, StockLocationReference::POSITION_SOURCE);
        $productIds = array_unique(array_merge(array_keys($incoming), array_keys($outgoing)));
        $stockFlow = [];
        foreach ($productIds as $productId) {
            if (!array_key_exists($productId, $stockFlow)) {
                $stockFlow[$productId] = new StockFlow(0, 0);
            }
            if (array_key_exists($productId, $incoming)) {
                $stockFlow[$productId]->incoming = (int) $incoming[$productId]['quantity'];
            }
            if (array_key_exists($productId, $outgoing)) {
                $stockFlow[$productId]->outgoing = (int) $outgoing[$productId]['quantity'];
            }
        }

        return $stockFlow;
    }

    public function getCombinedStockFlowPerStockLocationType($stockLocationReferences): array
    {
        $incoming = $this->calculateCombinedStockFlow($stockLocationReferences, StockLocationReference::POSITION_DESTINATION, true);
        $outgoing = $this->calculateCombinedStockFlow($stockLocationReferences, StockLocationReference::POSITION_SOURCE, true);
        $stockFlows = [
            ...$incoming,
            ...$outgoing,
        ];
        $combinedStockFlow = [];
        foreach ($stockFlows as $stockFlow) {
            $productId = $stockFlow['productId'];
            $isIncoming = array_key_exists('source_location_type_technical_name', $stockFlow);
            $locationType = $stockFlow['source_location_type_technical_name'] ?? $stockFlow['destination_location_type_technical_name'];
            $internalOrExternal = in_array($locationType, LocationTypeDefinition::TECHNICAL_NAMES_INTERNAL) ? 'internal' : 'external';
            if (!array_key_exists($productId, $combinedStockFlow)) {
                $combinedStockFlow[$productId] = [];
                $combinedStockFlow[$productId]['internal'] = new StockFlow(0, 0);
                $combinedStockFlow[$productId]['external'] = new StockFlow(0, 0);
            }
            if (!array_key_exists($locationType, $combinedStockFlow[$productId])) {
                $combinedStockFlow[$productId][$locationType] = new StockFlow(0, 0);
            }
            if ($isIncoming) {
                $combinedStockFlow[$productId][$locationType]->incoming = (int) $stockFlow['quantity'];
                $combinedStockFlow[$productId][$internalOrExternal]->incoming += (int) $stockFlow['quantity'];
            } else {
                $combinedStockFlow[$productId][$locationType]->outgoing = (int) $stockFlow['quantity'];
                $combinedStockFlow[$productId][$internalOrExternal]->outgoing += (int) $stockFlow['quantity'];
            }
        }

        return $combinedStockFlow;
    }

    /**
     * @param StockLocationReference[] $stockLocations
     * @param string $stockLocationPosition 'source', 'destination'
     */
    public function calculateCombinedStockFlow(array $stockLocations, string $stockLocationPosition, $groupByStockLocationType = false): array
    {
        $stockLocationConditions = [];
        $parameters = [];
        foreach ($stockLocations as $index => $stockLocation) {
            $referencingPrimaryKeyFieldName = $stockLocation->getDatabasePrimaryKeyFieldName($stockLocationPosition);
            // We need to set different stock location primary key. So suffix their query builder parameter to make them
            // unique.
            $stockLocationPrimaryKeyName = sprintf('stockLocationPrimaryKey%s', $index);
            $condition = sprintf('%s = :%s', $referencingPrimaryKeyFieldName, $stockLocationPrimaryKeyName);

            $referencingVersionFieldName = $stockLocation->getDatabaseVersionFieldName($stockLocationPosition);
            if ($referencingVersionFieldName) {
                // Not all non-special stock locations reference a version field
                $condition .= sprintf(' AND %s = :liveVersionId', $referencingVersionFieldName);
            }

            $stockLocationConditions[] = sprintf('(%s)', $condition);
            $stockLocationPrimaryKey = $stockLocation->getPrimaryKey();
            $parameters[$stockLocationPrimaryKeyName] = Uuid::isValid($stockLocationPrimaryKey) ? hex2bin($stockLocationPrimaryKey) : $stockLocationPrimaryKey;
        }

        $query = 'SELECT
              LOWER(HEX(`product_id`)) AS productId,
              SUM(`quantity`) AS quantity
            FROM `pickware_erp_stock_movement`
            WHERE `product_version_id` = :liveVersionId
            AND (' . implode(' OR ', $stockLocationConditions) . ')
            GROUP BY `product_id`';

        // If we want to fetch all stock movements that have the current stock location as source, we need to group by
        // all different destination location types (and vice versa)
        $locationTypeTechnicalName = $stockLocationPosition === StockLocationReference::POSITION_SOURCE ? 'destination_location_type_technical_name' : 'source_location_type_technical_name';

        if ($groupByStockLocationType) {
            $query = 'SELECT
              LOWER(HEX(`product_id`)) AS productId,
              SUM(`quantity`) AS quantity,
              `' . $locationTypeTechnicalName . '`
            FROM `pickware_erp_stock_movement`
            WHERE `product_version_id` = :liveVersionId
            AND (' . implode(' OR ', $stockLocationConditions) . ')
            GROUP BY `product_id`, `' . $locationTypeTechnicalName . '`';
        }

        $stockFlow = $this->connection->fetchAllAssociative(
            $query,
            array_merge(
                [
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                ],
                $parameters,
            ),
        );

        // The grouping is done later on
        if ($groupByStockLocationType) {
            return $stockFlow;
        }

        // Return stock flow by product id
        return array_combine(array_column($stockFlow, 'productId'), $stockFlow);
    }
}
