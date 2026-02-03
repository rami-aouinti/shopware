<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Warehouse\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareErpStarter\Warehouse\WarehouseError;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class WarehouseController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
        private readonly StockMovementService $stockMovementService,
    ) {}

    /**
     * Deletes a given warehouse when there is only "irrelevant stock" or no stock at all. "irrelevant stock" is stock
     * of products that are not stock managed. Or negative stock of other products. These stocks will be "fixed" by
     * moving stock until the stock in the warehouse is 0 in all stock locations.
     *
     * Otherwise, if there is "relevant stock", the deletion is not allowed.
     */
    #[Route(
        path: '/api/_action/pickware-erp/delete-warehouse',
        methods: ['POST'],
    )]
    public function deleteWarehouse(#[JsonParameterAsUuid] string $warehouseId, Context $context): Response
    {
        try {
            $this->entityManager->runInTransactionWithRetry(
                function() use ($warehouseId, $context): void {
                    $stockInWarehouseFilter = new MultiFilter(
                        MultiFilter::CONNECTION_OR,
                        [
                            new EqualsFilter('warehouseId', $warehouseId),
                            new EqualsFilter('binLocation.warehouseId', $warehouseId),
                        ],
                    );
                    $criteria = (new Criteria())
                        ->addFilter(new MultiFilter(
                            MultiFilter::CONNECTION_OR,
                            [
                                // Product that are not stockmanaged and have a quantity <> 0. Remember: not stockmanaged
                                // products can still have positive stock as well as negative stock.
                                new MultiFilter(
                                    MultiFilter::CONNECTION_AND,
                                    [
                                        $stockInWarehouseFilter,
                                        new EqualsFilter('product.pickwareErpPickwareProduct.isStockManagementDisabled', true),
                                        new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('quantity', 0)]),
                                    ],
                                ),
                                // Products that are stock managed but have negative stock due to some other process (e.g.
                                // third party imports).
                                new MultiFilter(
                                    MultiFilter::CONNECTION_AND,
                                    [
                                        $stockInWarehouseFilter,
                                        new EqualsFilter('product.pickwareErpPickwareProduct.isStockManagementDisabled', false),
                                        new RangeFilter('quantity', [RangeFilter::LT => 0]),
                                    ],
                                ),
                            ],
                        ));

                    /** @var StockCollection $stockIrrelevantForWarehouseDeletion */
                    $stockIrrelevantForWarehouseDeletion = $this->entityManager->findBy(
                        StockDefinition::class,
                        $criteria,
                        $context,
                    );

                    // "Fix" all irrelevant stock reverse-moving stock in each location so the stock in each location
                    // will be 0.
                    $stockMovements = array_values($stockIrrelevantForWarehouseDeletion->map(
                        fn(StockEntity $stock) => StockMovement::create([
                            'productId' => $stock->getProductId(),
                            'quantity' => -$stock->getQuantity(),
                            'destination' => $stock->getStockLocationReference(),
                            'source' => StockLocationReference::unknown(),
                        ]),
                    ));
                    $this->stockMovementService->moveStock($stockMovements, $context);

                    // Remove all quantity 0 stock entries, similar to the ProductStockUpdater::cleanUpStocks method. This
                    // includes the fixed-up stock entries from above. Afterwards, only relevant stock entries remain.
                    $this->connection->executeStatement(
                        'DELETE `stock`
                    FROM `pickware_erp_stock` AS `stock`
                    LEFT JOIN `pickware_erp_bin_location` AS `bin_location` ON `stock`.`bin_location_id` = `bin_location`.`id`
                    WHERE `stock`.`quantity` = 0
                    AND (
                        (`stock`.`location_type_technical_name` = "warehouse" AND `stock`.`warehouse_id` = :warehouseId) OR
                        (`stock`.`location_type_technical_name` = "bin_location" AND `bin_location`.`warehouse_id` = :warehouseId)
                    )
                    AND `stock`.`product_version_id` = :liveVersionId',
                        [
                            'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                            'warehouseId' => hex2bin($warehouseId),
                        ],
                    );

                    $this->entityManager->delete(WarehouseDefinition::class, [$warehouseId], $context);
                },
            );
        } catch (RestrictDeleteViolationException $e) {
            // If there is still stock in the warehouse in the unknown stock location, a
            // RestrictDeleteViolationException is thrown from Shopware's DAL. (restriction: warehouse -> stock)
            if (count($e->getRestrictions()) !== 1) {
                throw $e;
            }
            $restrictingTables = array_keys($e->getRestrictions()[0]->getRestrictions());
            if ($restrictingTables !== [StockDefinition::ENTITY_NAME]) {
                throw $e;
            }

            return WarehouseError::cannotBeDeletedDueToExistingStock($warehouseId)
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        } catch (ForeignKeyConstraintViolationException $e) {
            // If there is still stock in a bin location a foreign key constraint violation from the dbal connection is
            // thrown. This is true because Shopware's DAL only validates delete restrictions on the first level.
            // (i.e. not working for restriction: warehouse -> bin_location -> stock).
            // See https://github.com/shopware/shopware/commit/a8badf615b09c83d9869fc045d3349628ab48f47
            // The same is true for other stock locations inside the warehouse (e.g. stock container)
            if (!str_contains($e->getMessage(), 'pickware_erp_stock.fk.')) {
                throw $e;
            }

            return WarehouseError::cannotBeDeletedDueToExistingStock($warehouseId)
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse();
    }
}
