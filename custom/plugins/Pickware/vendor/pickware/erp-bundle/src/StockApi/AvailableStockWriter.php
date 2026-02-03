<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Config\Config;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Picking\PickingRequest;
use Pickware\PickwareErpStarter\Picking\PickingStrategyStockShortageException;
use Pickware\PickwareErpStarter\Picking\ProductOrthogonalPickingStrategy;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\Stocking\ProductOrthogonalStockingStrategy;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Stocking\StockingRequest;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseCollection;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;

class AvailableStockWriter
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
        private readonly Config $configService,
        private readonly ProductOrthogonalStockingStrategy $stockingStrategy,
        private readonly ProductOrthogonalPickingStrategy $pickingStrategy,
        private readonly StockMovementService $stockMovementService,
    ) {}

    public function setAvailableStockForProducts(
        ProductQuantityImmutableCollection $productQuantities,
        StockLocationReference $externalLocation,
        Context $context,
    ): void {
        RetryableTransaction::retryable($this->connection, function() use (
            $productQuantities,
            $externalLocation,
            $context,
        ): void {
            $productIds = $productQuantities->getProductIds()->asArray();
            $this->entityManager->lockPessimistically(
                ProductDefinition::class,
                ['id' => $productIds],
                $context,
            );
            // Calculate the available stock instead of using the `product.(available_)stock` fields, because this
            // service might be called inside a transaction that has already modified those fields for the product.
            $currentAvailableStock = $this->connection->fetchAllKeyValue(
                <<<SQL
                    SELECT
                        LOWER(HEX(`pickware_product`.`product_id`)) AS `productId`,
                        (
                            `pickware_product`.`physical_stock`
                            - `pickware_product`.`reserved_stock`
                            - `pickware_product`.`stock_not_available_for_sale`
                        ) AS `availableStock`
                    FROM `pickware_erp_pickware_product` AS `pickware_product`
                    WHERE
                        `pickware_product`.`product_id` IN (:productIds)
                        AND `pickware_product`.`product_version_id` = :liveVersionId;
                    SQL,
                [
                    'productIds' => array_map(hex2bin(...), $productIds),
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                ],
                [
                    'productIds' => ArrayParameterType::BINARY,
                    'liveVersionId' => ParameterType::BINARY,
                ],
            );

            if (count($currentAvailableStock) !== count($productIds)) {
                // Do not throw an AvailableStockWriterException here, because this is an error the user cannot fix.
                throw new InvalidArgumentException(sprintf(
                    'The product(s) "%s" could not be found in the database.',
                    implode('", "', array_diff($productIds, array_keys($currentAvailableStock))),
                ));
            }

            $stockingQuantities = [];
            $pickingQuantities = [];
            foreach ($productQuantities as $productQuantity) {
                $productId = $productQuantity->getProductId();
                $quantityToMove = $productQuantity->getQuantity() - (int) $currentAvailableStock[$productId];
                if ($quantityToMove > 0) {
                    $stockingQuantities[] = new ProductQuantity($productId, $quantityToMove);
                } elseif ($quantityToMove < 0) {
                    $pickingQuantities[] = new ProductQuantity($productId, -$quantityToMove);
                }
            }

            // We cannot use StockArea::everywhere(), because only warehouses that are available for sale should be
            // considered when changing the available stock. Use the first one for stocking, and all for picking.
            $availableWarehouses = $this->getAvailableWarehouses($context);
            $stockMovements = [
                ...$this->stockProductsInAvailableWarehouse(
                    new ProductQuantityImmutableCollection($stockingQuantities),
                    $externalLocation,
                    $availableWarehouses->first(),
                    $context,
                ),
                ...$this->pickProductsFromAvailableWarehouses(
                    new ProductQuantityImmutableCollection($pickingQuantities),
                    $externalLocation,
                    $availableWarehouses,
                    $context,
                ),
            ];
            if (count($stockMovements) > 0) {
                $this->writeStockMovements($stockMovements, $context);
            }
        });
    }

    private function getAvailableWarehouses(Context $context): WarehouseCollection
    {
        $availableWarehouses = $this->entityManager->findBy(
            WarehouseDefinition::class,
            ['isStockAvailableForSale' => true],
            $context,
        );

        if (count($availableWarehouses) === 0) {
            throw NoWarehousesAvailableForSaleException::noAvailableWarehouses();
        }

        // Sort warehouses by default warehouse first, then by creation date.
        $availableWarehouses->sort(function(WarehouseEntity $lhs, WarehouseEntity $rhs) {
            if ($lhs->getId() !== $rhs->getId() && $lhs->getId() === $this->configService->getDefaultWarehouseId()) {
                return -1;
            }
            if ($lhs->getId() !== $rhs->getId() && $rhs->getId() === $this->configService->getDefaultWarehouseId()) {
                return 1;
            }

            return $lhs->getCreatedAt() <=> $rhs->getCreatedAt();
        });

        return new WarehouseCollection($availableWarehouses);
    }

    /**
     * @return StockMovement[]
     */
    private function stockProductsInAvailableWarehouse(
        ProductQuantityImmutableCollection $productQuantities,
        StockLocationReference $sourceLocationReference,
        WarehouseEntity $warehouse,
        Context $context,
    ): array {
        $stockingRequest = new StockingRequest(
            productQuantities: $productQuantities->groupByProductId(),
            stockArea: StockArea::warehouse($warehouse->getId()),
        );

        return $this->stockingStrategy
            ->calculateStockingSolution($stockingRequest, $context)
            ->createStockMovementsWithSource($sourceLocationReference);
    }

    /**
     * @return StockMovement[]
     */
    private function pickProductsFromAvailableWarehouses(
        ProductQuantityImmutableCollection $productQuantities,
        StockLocationReference $destinationLocationReference,
        WarehouseCollection $warehouses,
        Context $context,
    ): array {
        $pickingRequest = new PickingRequest(
            productQuantities: $productQuantities->groupByProductId(),
            sourceStockArea: StockArea::warehouses(array_values($warehouses->getIds())),
        );

        try {
            return $this->pickingStrategy
                ->calculatePickingSolution($pickingRequest, $context)
                ->createStockMovementsWithDestination($destinationLocationReference);
        } catch (PickingStrategyStockShortageException $e) {
            throw AvailableStockWriterException::notEnoughStock(
                $e->getStockShortages()->getProductIds()->asArray(),
                $e,
            );
        }
    }

    /**
     * @param StockMovement[] $stockMovements
     */
    private function writeStockMovements(array $stockMovements, Context $context): void
    {
        try {
            $this->stockMovementService->moveStock($stockMovements, $context);
        } catch (OperationLeadsToNegativeStocksException $e) {
            throw AvailableStockWriterException::notEnoughStock($e->getProductIds(), $e);
        }
    }
}
