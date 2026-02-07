<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderPickability\Model\OrderPickabilityDefinition;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\Picking\OrderQuantitiesToShipCalculator;
use Pickware\PickwareErpStarter\Picking\ProductsToShipCalculator;
use Pickware\PickwareWms\StockReservation\MemoryReservedStockExcludingPickableStockProvider;
use Pickware\PickwareWms\StockReservation\ProductQuantityLocationImmutableCollectionExtension;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;

class PickableOrderService
{
    /**
     * @param ProductsToShipCalculator|null $productToShipCalculatorToShipCalculator Maintained for backwards compatibility with ERP, will be removed after WMS minimum requirement of ERP-4.4.0
     * @param OrderQuantitiesToShipCalculator|null $orderQuantitiesToShipCalculator Marked optional to maintain backwards compatibility with ERP, will be non-optional after WMS minimum requirement of ERP-4.4.0
     */
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly MemoryReservedStockExcludingPickableStockProvider $stockProvider,
        private readonly ?ProductsToShipCalculator $productToShipCalculatorToShipCalculator = null,
        private readonly ?OrderQuantitiesToShipCalculator $orderQuantitiesToShipCalculator = null,
    ) {}

    /**
     * @return string[]
     */
    public function findNextPickableOrderIds(
        Criteria $orderCriteria,
        int $targetCount,
        Context $context,
    ): array {
        return $this->findNextPickableOrderIdsWithFetchStrategy(
            $orderCriteria,
            $targetCount,
            $context,
            fn(Criteria $orderCriteria, Context $context) => $this->entityManager->lockPessimistically(
                OrderDefinition::class,
                $orderCriteria,
                $context,
            ),
        );
    }

    /**
     * @return string[]
     */
    public function findNextPickableOrderIdsForSingleItemPicking(
        Criteria $orderCriteria,
        int $targetCount,
        Context $context,
    ): array {
        return $this->findNextPickableOrderIdsWithFetchStrategy(
            $orderCriteria,
            $targetCount,
            $context,
            function(Criteria $orderCriteria, Context $context) {
                $queryBuilder = $this->entityManager->createQueryBuilder(
                    OrderDefinition::class,
                    $orderCriteria,
                    $context,
                );
                $queryBuilder->leftJoin(
                    fromAlias: '`order`',
                    join: 'order_line_item',
                    alias: 'order_line_items',
                    condition: <<<SQL
                        `order`.`id` = `order_line_items`.`order_id`
                        AND `order`.`version_id` = `order_line_items`.`order_version_id`
                        AND `order_line_items`.`type` = 'product'
                        SQL,
                );
                $queryBuilder->leftJoin(
                    fromAlias: 'order_line_items',
                    join: 'pickware_erp_pickware_order_line_item',
                    alias: 'pickware_order_line_items',
                    condition: <<<SQL
                        `order_line_items`.`id` = `pickware_order_line_items`.`order_line_item_id`
                        AND `order_line_items`.`version_id` = `pickware_order_line_items`.`order_line_item_version_id`
                        SQL,
                );
                $queryBuilder->groupBy('`order`.`id`');
                $queryBuilder->andHaving('SUM(`order_line_items`.`quantity` - COALESCE(`pickware_order_line_items`.`externally_fulfilled_quantity`, 0)) = 1');
                $queryBuilder->addSelect('LOWER(HEX(`order`.`id`)) AS `id`');
                $queryBuilder->addSelect('COUNT(*) OVER (PARTITION BY `order_line_items`.`product_id`) AS `count_of_orders_with_same_product`');
                $queryBuilder->orderBy('count_of_orders_with_same_product', 'DESC');
                $queryBuilder->addOrderBy('`order`.`created_at`', 'ASC');

                return $this->entityManager->lockPessimisticallyWithQueryBuilder($queryBuilder);
            },
        );
    }

    /**
     * @param callable(Criteria, Context): string[] $fetchOrderIds
     * @return string[]
     */
    private function findNextPickableOrderIdsWithFetchStrategy(
        Criteria $orderCriteria,
        int $targetCount,
        Context $context,
        callable $fetchOrderIds,
    ): array {
        // Extract warehouseId and allowed pickability status from criteria filters
        [$warehouseId, $allowedPickabilityStatus] = $this->findOrderPickabilityStatusFilter($orderCriteria->getFilters());
        if (!$warehouseId || !$allowedPickabilityStatus) {
            throw PickingProcessException::missingOrInvalidOrderPickabilityFilter();
        }

        $resultOrderIds = [];
        $this->stockProvider->keepReservedStocksInCallback(function() use (
            $orderCriteria,
            $targetCount,
            &$resultOrderIds,
            $allowedPickabilityStatus,
            $warehouseId,
            $context,
            $fetchOrderIds,
        ): void {
            // Tracing showed that it is more efficient to fetch more orders as required, which reduces iterations.
            $limit = $targetCount * 2;
            for ($i = 0; $i < 3; $i++) {
                // Does three iterations of fetching orders. This is used as a heuristic to find as many orders as
                // possible within a fixed, limited iteration count which are batch pickable together. Determining this
                // ahead of time is not straightforward as including one order may impact the pickability of future
                // orders and thus impacts if they are included or not.
                $remainingOrders = $targetCount - count($resultOrderIds);
                if ($remainingOrders === 0) {
                    break;
                }

                $limitedOrderCriteria = (clone $orderCriteria)
                    ->setLimit($limit)
                    ->setOffset($limit * $i);

                $orderIds = $fetchOrderIds($limitedOrderCriteria, $context);

                $productsToShipByOrderId = $this->calculateProductsToShipForOrders($orderIds, $context);

                $productIds = ImmutableCollection::create($productsToShipByOrderId)
                    ->flatMap(
                        fn(ProductQuantityImmutableCollection $productQuantities) => $productQuantities->getProductIds(),
                    )
                    ->deduplicate()
                    ->asArray();
                $pickableStock = $this->stockProvider->getPickableStocks($productIds, [$warehouseId], $context);

                foreach ($orderIds as $orderId) {
                    /** @var ?ProductQuantityLocationImmutableCollection $productQuantitiesToPick */
                    $productQuantitiesToPick = $this->getProductsToPick(
                        $productsToShipByOrderId[$orderId],
                        $pickableStock,
                        $allowedPickabilityStatus,
                    );

                    if ($productQuantitiesToPick?->isEmpty() === false) {
                        $resultOrderIds[] = $orderId;
                        $this->stockProvider->addToReservedStock($productQuantitiesToPick);

                        $pickableStock = ProductQuantityLocationImmutableCollectionExtension::subtract(
                            $pickableStock,
                            $productQuantitiesToPick,
                        );
                    }

                    if (count($resultOrderIds) === $targetCount) {
                        break;
                    }
                }

                if (count($orderIds) < $limit) {
                    // No more orders available, so no iterations have to be done here
                    break;
                }
            }
        });

        return $resultOrderIds;
    }

    /**
     * @param string[] $orderIds
     * @return array<string, ProductQuantityImmutableCollection>
     */
    private function calculateProductsToShipForOrders(array $orderIds, Context $context): array
    {
        // Old calculation logic maintained for backwards compatibility with ERP
        // Can be removed after WMS minimum requirement of ERP-4.4.0
        if ($this->orderQuantitiesToShipCalculator === null) {
            return $this->productToShipCalculatorToShipCalculator->calculateProductsToShipForOrders(
                orderIds: $orderIds,
                context: $context,
            );
        }

        return $this->orderQuantitiesToShipCalculator->calculateProductsToShipForOrders(
            orderIds: $orderIds,
            context: $context,
        );
    }

    /**
     * Recursively searches for the first order pickability filter in the given filters. An order pickability filter
     * must have the form of a multi filter with two queries:
     *   - `EqualsFilter` for the `warehouseId`
     *   - `EqualsAnyFilter` for the `orderPickabilityStatus`
     *
     * @param Filter[] $filters
     * @return array{0: string|null, 1: string[]|null}
     */
    private function findOrderPickabilityStatusFilter(array $filters): array
    {
        foreach ($filters as $filter) {
            if (!($filter instanceof MultiFilter) && !is_subclass_of($filter, MultiFilter::class)) {
                continue;
            }

            /** @var MultiFilter $filter */
            $warehouseId = null;
            $orderPickabilityStatus = null;
            foreach ($filter->getQueries() as $query) {
                if ($query instanceof EqualsFilter && str_ends_with($query->getField(), 'pickwareErpOrderPickabilities.warehouseId')) {
                    if ($warehouseId !== null) {
                        throw PickingProcessException::missingOrInvalidOrderPickabilityFilter();
                    }
                    $warehouseId = $query->getValue();
                }
                if ($query instanceof EqualsAnyFilter && str_ends_with($query->getField(), 'pickwareErpOrderPickabilities.orderPickabilityStatus')) {
                    if ($orderPickabilityStatus !== null) {
                        throw PickingProcessException::missingOrInvalidOrderPickabilityFilter();
                    }
                    $orderPickabilityStatus = $query->getValue();
                }
            }
            if ($warehouseId && $orderPickabilityStatus) {
                return [
                    $warehouseId,
                    $orderPickabilityStatus,
                ];
            }

            $result = $this->findOrderPickabilityStatusFilter($filter->getQueries());
            if ($result[0] && $result[1]) {
                return $result;
            }
        }

        return [
            null,
            null,
        ];
    }

    /**
     * @param string[] $allowedPickabilityStatus
     */
    private function getProductsToPick(
        ProductQuantityImmutableCollection $productsToShip,
        ProductQuantityLocationImmutableCollection $pickableStock,
        array $allowedPickabilityStatus,
    ): ?ProductQuantityLocationImmutableCollection {
        $productsToPickByProductId = [];
        foreach ($productsToShip as $productToShip) {
            $productId = $productToShip->getProductId();
            $stockToPickForProduct = 0;
            foreach ($pickableStock as $stock) {
                if ($stock->getProductId() !== $productToShip->getProductId()) {
                    continue;
                }

                $remainingStockToPickForProduct = min(
                    $productToShip->getQuantity() - $stockToPickForProduct,
                    $stock->getQuantity(),
                );
                if ($remainingStockToPickForProduct <= 0) {
                    break;
                }

                $productsToPickByProductId[$productId] ??= [];
                $productsToPickByProductId[$productId][] = new ProductQuantityLocation(
                    $stock->getStockLocationReference(),
                    $stock->getProductId(),
                    $remainingStockToPickForProduct,
                );
                $stockToPickForProduct += $remainingStockToPickForProduct;
            }
        }

        if (
            !in_array(
                $this->getPickabilityStatusForProductsToShip($productsToShip, $productsToPickByProductId),
                $allowedPickabilityStatus,
                strict: true,
            )
        ) {
            return null;
        }

        return new ProductQuantityLocationImmutableCollection(array_merge(
            ...array_values($productsToPickByProductId),
        ));
    }

    /**
     * @param ProductQuantityLocation[][] $productsToPickByProductId
     */
    private function getPickabilityStatusForProductsToShip(
        ProductQuantityImmutableCollection $productsToShip,
        array $productsToPickByProductId,
    ): string {
        $isRequestCompletelyPickable = true;
        $isAtLeastOneItemCompletelyPickable = false;
        foreach ($productsToShip as $productToShip) {
            $productsToPick = new ImmutableCollection($productsToPickByProductId[$productToShip->getProductId()] ?? []);
            // Resembles a subset of the available stock planned for picking this product picking request.
            $totalQuantityAvailableForProduct = array_reduce(
                $productsToPick->asArray(),
                fn(int $sum, ProductQuantityLocation $productQuantityLocation) => $sum + $productQuantityLocation->getQuantity(),
                0,
            );
            if ($totalQuantityAvailableForProduct < $productToShip->getQuantity()) {
                $isRequestCompletelyPickable = false;
                if ($totalQuantityAvailableForProduct > 0) {
                    // If one product is partially pickable, the complete order is partially pickable, we don't need to
                    // test the other products.
                    return OrderPickabilityDefinition::PICKABILITY_STATUS_PARTIALLY_PICKABLE;
                }
            } else {
                $isAtLeastOneItemCompletelyPickable = true;
            }
        }

        if ($isRequestCompletelyPickable) {
            return OrderPickabilityDefinition::PICKABILITY_STATUS_COMPLETELY_PICKABLE;
        }

        if ($isAtLeastOneItemCompletelyPickable) {
            return OrderPickabilityDefinition::PICKABILITY_STATUS_PARTIALLY_PICKABLE;
        }

        return OrderPickabilityDefinition::PICKABILITY_STATUS_NOT_PICKABLE;
    }
}
