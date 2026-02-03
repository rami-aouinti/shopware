<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking;

use DateTime;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\ExceptionHandling\UniqueIndexHttpException;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeCollection;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeCountingProcessDefinition;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeCountingProcessEntity;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeCountingProcessItemDefinition;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeCountingProcessItemEntity;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeDefinition;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationCollection;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserDefinition;

class StocktakingService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly StocktakingStockChangeService $stocktakingStockChangeService,
        private readonly ProductNameFormatterService $productNameFormatterService,
    ) {}

    /**
     * @param string|null $binLocationId bin location id or null if it is the unknown stock location in a warehouse
     * @return list<string> List of product ids that have stock in the given stock location and that are not (yet) counted in
     * the given stocktake. Can be empty if there are no products with stock, or when all products are already counted.
     */
    public function getUncountedProductsInStockLocation(
        string $stocktakeId,
        ?string $binLocationId,
        int $limit,
        Context $context,
    ): array {
        if ($binLocationId) {
            $warehouseFilter = 'IS NULL';
            $binLocationFilter = sprintf('= UNHEX("%s")', $binLocationId);
            $locationTypeTechnicalName = LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION;
        } else {
            $stocktake = $this->entityManager->findByPrimaryKey(StocktakeDefinition::class, $stocktakeId, $context);
            if (!$stocktake) {
                throw StocktakingException::stocktakesNotFound([$stocktakeId]);
            }
            $warehouseFilter = sprintf('= UNHEX("%s")', $stocktake->getWarehouseId());
            $binLocationFilter = 'IS NULL';
            $locationTypeTechnicalName = LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE;
        }

        return $this->connection->fetchFirstColumn(
            'SELECT

            DISTINCT(LOWER(HEX(stock.`product_id`))) as productId

            FROM `pickware_erp_stock` stock

            LEFT JOIN `product`
            ON `product`.`id` = stock.`product_id`
            AND `product`.`version_id` = stock.`product_version_id`

            LEFT JOIN (
                SELECT
                    MAX(countingProcessItem.`product_id`) AS `product_id`,
                    MAX(countingProcessItem.`product_version_id`) AS `product_version_id`,
                    COUNT(*) AS numberOfCountingsInStockLocation

                FROM `pickware_erp_stocktaking_stocktake_counting_process_item` countingProcessItem

                LEFT JOIN `pickware_erp_stocktaking_stocktake_counting_process` countingProcess
                ON countingProcess.`id`= countingProcessItem.`counting_process_id`

                WHERE countingProcess.`stocktake_id` = UNHEX(:stocktakeId)
                AND countingProcess.`bin_location_id` ' . $binLocationFilter . '

                GROUP BY countingProcessItem.`product_id`, countingProcessItem.`product_version_id`
            ) as existingCountings
            ON existingCountings.`product_id` = product.`id`
            AND existingCountings.`product_version_id` = product.`version_id`

            WHERE stock.`location_type_technical_name` = :locationTypeTechnicalName
            AND stock.`warehouse_id` ' . $warehouseFilter . '
            AND stock.`bin_location_id` ' . $binLocationFilter . '
            AND (existingCountings.numberOfCountingsInStockLocation IS NULL OR existingCountings.numberOfCountingsInStockLocation = 0)
            ORDER BY `product`.`product_number` ASC
            LIMIT ' . $limit,
            [
                'locationTypeTechnicalName' => $locationTypeTechnicalName,
                'binLocationId' => $binLocationId,
                'stocktakeId' => $stocktakeId,
            ],
        );
    }

    /**
     * @param array $countingProcessPayloads multiple payloads of counting process entities
     * @return string[] ids of the created counting process entities
     * @throws StocktakingException
     */
    public function upsertCountingProcesses(array $countingProcessPayloads, Context $context): array
    {
        $stocktakeIds = array_unique(array_column($countingProcessPayloads, 'stocktakeId'));
        $binLocationIds = array_unique(array_column($countingProcessPayloads, 'binLocationId'));
        $productIds = array_unique(array_merge(...array_map(fn($payload) => array_column($payload['items'] ?? [], 'productId'), $countingProcessPayloads)));

        $stocktakes = $this->entityManager->findBy(StocktakeDefinition::class, ['id' => $stocktakeIds], $context);
        if ($stocktakes->count() !== count($stocktakeIds)) {
            throw StocktakingException::stocktakesNotFound(array_diff($stocktakeIds, $stocktakes->getIds()));
        }

        // Validate that no bin location is counted multiple times within one request
        $stocktakeIdBinLocationIdPairs = array_map(fn($payload) => $payload['stocktakeId'] . ($payload['binLocationId'] ?? ''), $countingProcessPayloads);
        $uniqueStocktakeIdBinLocationIdPairs = array_unique($stocktakeIdBinLocationIdPairs);
        if (count($stocktakeIdBinLocationIdPairs) !== count($uniqueStocktakeIdBinLocationIdPairs)) {
            throw new InvalidArgumentException('There are multiple counting processes for the same stocktake with the same bin location id in the payload.');
        }

        // Validate that no product is counted multiple times within one stock location
        $countingProcessesWithDuplicateProducts = array_filter($countingProcessPayloads, function($countingProcessPayload) {
            $productIds = array_column($countingProcessPayload['items'] ?? [], 'productId');

            return count($productIds) !== count(array_unique($productIds));
        });
        if (!empty($countingProcessesWithDuplicateProducts)) {
            throw new InvalidArgumentException('A product with the same id is counted multiple times in the same counting process.');
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsAnyFilter('stocktakeId', $stocktakeIds))
            ->addAssociation('items')
            ->addAssociation('binLocation')
            ->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING))
            ->addSorting(new FieldSorting('items.createdAt', FieldSorting::ASCENDING));

        // We want to merge the new counting into existing counting processes when we find a suitable match.
        // For matching and merging a counting process in the unknown stock location, we need to consider counting
        // processes that contain at least one product from the payload. Otherwise, a new counting process is created.
        // For matching and merging a counting process in a bin locations, we must consider the counting processes with
        // the same bin location regardless of the products that are counted.
        if (in_array(null, $binLocationIds, true)) {
            $criteria->addFilter(new OrFilter([
                new AndFilter([
                    new EqualsFilter('binLocation.id', null),
                    new EqualsAnyFilter('items.productId', $productIds),
                ]),
                new EqualsAnyFilter('binLocationId', array_filter($binLocationIds)),
            ]));
        } else {
            $criteria->addFilter(new EqualsAnyFilter('binLocationId', array_filter($binLocationIds)));
        }

        $existingCountingProcesses = $this->entityManager->findBy(
            StocktakeCountingProcessDefinition::class,
            $criteria,
            $context,
        );

        $countingProcessIdsWithDeletedItems = [];
        $countingProcessItemIdsToDelete = [];
        $countingProcessIdsToDelete = [];
        $countingProcessesCreatePayloads = $countingProcessPayloads;

        foreach ($countingProcessesCreatePayloads as &$countingProcessPayload) {
            $productIds = array_column($countingProcessPayload['items'] ?? [], 'productId');

            // Refer to the comment above (criteria) for the explanation of the following logic. It's the same.
            $matchingExistingProcesses = $existingCountingProcesses->filter(function(StocktakeCountingProcessEntity $countingProcess) use ($countingProcessPayload, $productIds) {
                $isBinLocationNull = $countingProcess->getBinLocationId() === null;
                $hasMatchingProducts = count(array_intersect($productIds, array_column($countingProcess->getItems()->getElements(), 'productId'))) > 0;

                return $countingProcess->getStocktakeId() === $countingProcessPayload['stocktakeId']
                    && $countingProcess->getBinLocationId() === $countingProcessPayload['binLocationId']
                    && (!$isBinLocationNull || $hasMatchingProducts);
            });

            if ($matchingExistingProcesses->count() === 0) {
                // When no matching counting process exists, the payload is passed along as new (create)
                continue;
            }

            if ($countingProcessPayload['binLocationId'] === null) {
                $matchingExistingProcessesItems = array_merge(
                    ...array_map(
                        fn(StocktakeCountingProcessEntity $countingProcess) => $countingProcess->getItems()->getElements(),
                        array_values($matchingExistingProcesses->getElements()),
                    ),
                );

                foreach ($countingProcessPayload['items'] as &$countingProcessesItem) {
                    // There is at most one matching existing process item for a product in the unknown stock location
                    $matchingExistingProcessesItem = array_values(
                        array_filter(
                            $matchingExistingProcessesItems,
                            fn(StocktakeCountingProcessItemEntity $item) => $item->getProductId() === $countingProcessesItem['productId'],
                        ),
                    )[0] ?? null;

                    if ($matchingExistingProcessesItem) {
                        // The product is counted again, the quantities will be added. The counting process item will be
                        // deleted and the quantity added to the new counting process item.
                        // Track the process for which the item will be deleted. If the process is then empty, we have
                        // to delete it as well.
                        $countingProcessesItem['quantity'] += $matchingExistingProcessesItem->getQuantity();
                        $countingProcessItemIdsToDelete[] = $matchingExistingProcessesItem->getId();
                        $countingProcessIdsWithDeletedItems[] = $matchingExistingProcessesItem->getCountingProcessId();
                    }

                    unset($countingProcessesItem);
                }
            } else {
                // The counting process will be deleted, the new counting process will be saved in its favor.
                $countingProcessIdsToDelete[] = $matchingExistingProcesses->first()->getId();

                // Counting process items (products) that are counted anew will be deleted with the counting process.
                // Items that are _not_ counted again will be "moved" to the new counting process by creating a new item
                // with copied values.
                $notAgainCountedProcessItems = $matchingExistingProcesses->first()
                    ->getItems()
                    ->filter(fn(StocktakeCountingProcessItemEntity $item) => !in_array(
                        $item->getProductId(),
                        array_column($countingProcessPayload['items'], 'productId'),
                        true,
                    ))
                    ->getElements();

                foreach ($notAgainCountedProcessItems as $item) {
                    /** @var $item StocktakeCountingProcessItemEntity */
                    $countingProcessPayload['items'][] = [
                        'id' => Uuid::randomHex(),
                        'productId' => $item->getProductId(),
                        'stockInStockLocationSnapshot' => $item->getStockInStockLocationSnapshot(),
                        'quantity' => $item->getQuantity(),
                        'createdAt' => $item->getCreatedAt(),
                    ];
                }
            }

            unset($countingProcessPayload);
        }

        $this->processCountingProcessPayloads($countingProcessesCreatePayloads, $context);

        RetryableTransaction::retryable($this->connection, function() use (
            $countingProcessItemIdsToDelete,
            $countingProcessIdsToDelete,
            $countingProcessesCreatePayloads,
            $countingProcessIdsWithDeletedItems,
            $context
        ): void {
            // The deletion of counting process items was not part of the process before. To ensure backwards
            // compatibility to the WMS app (ACL privileges) we need to run this deletion in the system scope. Since the
            // input is a list of ids that we created in this service, the input is safe (i.e. not from user-land).
            $context->scope(
                Context::SYSTEM_SCOPE,
                function(Context $systemScopeContext) use ($countingProcessItemIdsToDelete): void {
                    $this->entityManager->delete(
                        StocktakeCountingProcessItemDefinition::class,
                        $countingProcessItemIdsToDelete,
                        $systemScopeContext,
                    );
                },
            );
            $this->entityManager->delete(
                StocktakeCountingProcessDefinition::class,
                $countingProcessIdsToDelete,
                $context,
            );
            $this->entityManager->create(
                StocktakeCountingProcessDefinition::class,
                $countingProcessesCreatePayloads,
                $context,
            );

            // Delete counting processes for the unknown stock location that have no items
            if (count($countingProcessIdsWithDeletedItems) > 0) {
                $updatedCountingProcesses = $this->entityManager->findBy(
                    StocktakeCountingProcessDefinition::class,
                    ['id' => $countingProcessIdsWithDeletedItems],
                    $context,
                    ['items'],
                );

                $emptyCountingProcesses = $updatedCountingProcesses->filter(fn(StocktakeCountingProcessEntity $countingProcess) => count($countingProcess->getItems()->getElements()) === 0);

                if ($emptyCountingProcesses->count() > 0) {
                    $this->entityManager->delete(
                        StocktakeCountingProcessDefinition::class,
                        $emptyCountingProcesses->map(fn(StocktakeCountingProcessEntity $countingProcess) => $countingProcess->getId()),
                        $context,
                    );
                }
            }
        });

        return array_column($countingProcessesCreatePayloads, 'id');
    }

    /**
     * @param array $countingProcessPayloads multiple payloads of counting process entities
     * @return string[] ids of the created counting process entities
     * @throws StocktakingException
     */
    public function createCountingProcesses(array $countingProcessPayloads, Context $context): array
    {
        $stocktakeIds = array_unique(array_column($countingProcessPayloads, 'stocktakeId'));
        $stocktakes = $this->entityManager->findBy(StocktakeDefinition::class, ['id' => $stocktakeIds], $context);
        if ($stocktakes->count() !== count($stocktakeIds)) {
            throw StocktakingException::stocktakesNotFound(array_diff($stocktakeIds, $stocktakes->getIds()));
        }

        $binLocationIds = array_filter(array_unique(array_column($countingProcessPayloads, 'binLocationId')));
        $binLocations = new BinLocationCollection([]);
        if (count($binLocationIds) > 0) {
            $binLocations = $this->entityManager->findBy(
                BinLocationDefinition::class,
                ['id' => $binLocationIds],
                $context,
                ['warehouse'],
            );
        }

        $this->processCountingProcessPayloads($countingProcessPayloads, $context);

        try {
            $this->entityManager->createIfNotExists(
                StocktakeCountingProcessDefinition::class,
                $countingProcessPayloads,
                $context,
            );
        } catch (UniqueIndexHttpException $e) {
            if ($e->getErrorCode() !== CountingProcessUniqueIndexExceptionHandler::ERROR_CODE_STOCKTAKE_DUPLICATE_BIN_LOCATION) {
                throw $e;
            }

            throw StocktakingException::countingProcessForAtLeastOneBinLocationAlreadyExists(
                $binLocations->map(fn(BinLocationEntity $binLocation) => $binLocation->getCode()),
            );
        }

        return array_column($countingProcessPayloads, 'id');
    }

    private function processCountingProcessPayloads(array &$countingProcessPayloads, Context $context): void
    {
        /** @var StocktakeCollection $stocktakes */
        $stocktakes = $this->entityManager->findBy(
            StocktakeDefinition::class,
            ['id' => array_unique(array_column($countingProcessPayloads, 'stocktakeId'))],
            $context,
        );

        // Note that (non-null) binLocationIds can be empty if the user saves counting processes for only the unknown
        // stock location in the warehouse.
        $binLocationIds = array_filter(array_unique(array_column($countingProcessPayloads, 'binLocationId')));
        $binLocations = new BinLocationCollection([]);
        if (count($binLocationIds) > 0) {
            $binLocations = $this->entityManager->findBy(
                BinLocationDefinition::class,
                ['id' => $binLocationIds],
                $context,
                ['warehouse'],
            );
        }

        /** @var UserCollection $users */
        $users = $this->entityManager->findBy(
            UserDefinition::class,
            ['id' => array_unique(array_column($countingProcessPayloads, 'userId'))],
            $context,
        );

        $warehouseIds = $stocktakes->map(fn(StocktakeEntity $stocktake) => $stocktake->getWarehouseId());
        $stocksInStockLocationCriteria = (new Criteria())
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new MultiFilter(MultiFilter::CONNECTION_AND, [
                    new EqualsFilter('locationType.technicalName', LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE),
                    new EqualsAnyFilter('warehouseId', $warehouseIds),
                ]),
                new MultiFilter(MultiFilter::CONNECTION_AND, [
                    new EqualsFilter('locationType.technicalName', LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION),
                    new EqualsAnyFilter('binLocationId', $binLocationIds),
                ]),
            ]));
        /** @var StockCollection $stocksInStockLocation */
        $stocksInStockLocation = $this->entityManager->findBy(StockDefinition::class, $stocksInStockLocationCriteria, $context);

        $productsById = [];
        foreach ($countingProcessPayloads as &$countingProcessPayload) {
            if (isset($countingProcessPayload['stocktake'])) {
                throw new InvalidArgumentException('A stocktake can be passed by ID only.');
            }
            $stocktakeId = $countingProcessPayload['stocktakeId'] ?? null;
            if ($stocktakeId === null) {
                throw new InvalidArgumentException('Missing key "stocktakeId".');
            }

            $stocktake = $stocktakes->get($stocktakeId);
            if (!$stocktake) {
                throw new InvalidArgumentException(sprintf('No stocktake found with id %s', $stocktakeId));
            }
            if (!$stocktake->isActive()) {
                throw StocktakingException::stocktakeNotActive($stocktakeId, $stocktake->getTitle());
            }

            if (!isset($countingProcessPayload['items'])) {
                $countingProcessPayload['items'] = [];
            }

            if (isset($countingProcessPayload['binLocation'])) {
                throw new InvalidArgumentException('A bin location can be passed by ID only.');
            }
            if (isset($countingProcessPayload['binLocationId'])) {
                $binLocationId = $countingProcessPayload['binLocationId'];
                $binLocation = $binLocations->get($binLocationId);
                if (!$binLocation) {
                    throw new InvalidArgumentException(sprintf('No bin location found with id %s', $binLocationId));
                }
                $countingProcessPayload['binLocationSnapshot'] = [
                    'code' => $binLocation->getCode(),
                    'warehouseName' => $binLocation->getWarehouse()->getName(),
                    'warehouseCode' => $binLocation->getWarehouse()->getCode(),
                ];

                // If a bin location was counted (not the unknown stock location in warehouse), _all_ products will be
                // part of the stocktake. So if there are products that have stock in that bin location, that are _not_
                // part of the counting processes, they will be added as counting process item with quantity 0.
                // In other words: Products that are not explicitly counted, will be counted with quantity 0.
                $countedProductIds = array_column($countingProcessPayload['items'], 'productId');
                $uncountedStocksInBinLocation = $stocksInStockLocation->filter(fn(StockEntity $stock) => (
                    $stock->getBinLocationId() === $binLocationId
                    && !in_array($stock->getProductId(), $countedProductIds)
                ));
                foreach ($uncountedStocksInBinLocation as $uncountedStockInBinLocation) {
                    $countingProcessPayload['items'][] = [
                        'id' => Uuid::randomHex(),
                        'productId' => $uncountedStockInBinLocation->getProductId(),
                        'quantity' => 0,
                    ];
                }
            } else {
                $countingProcessPayload['binLocationSnapshot'] = null;
            }
            if (isset($countingProcessPayload['user'])) {
                throw new InvalidArgumentException('A user can be passed by ID only.');
            }
            if (isset($countingProcessPayload['userId'])) {
                $user = $users->get($countingProcessPayload['userId']);
                if (!$user) {
                    throw new InvalidArgumentException(sprintf('No user found with id %s', $countingProcessPayload['userId']));
                }
                $countingProcessPayload['userSnapshot'] = [
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                ];
            } else {
                $countingProcessPayload['userSnapshot'] = [];
            }

            // Fetch and format all product names in batches for each counting process. We cannot do this for _all_
            // counting processes at the beginning, because some products are added dynamically in the loop.
            $productIds = [];
            foreach ($countingProcessPayload['items'] as $countingProcessItem) {
                if (isset($countingProcessItem['productId'])) {
                    $productIds[] = $countingProcessItem['productId'];
                }
            }
            $productNamesByProductId = $this->productNameFormatterService->getFormattedProductNames($productIds, [], $context);

            foreach ($countingProcessPayload['items'] as &$countingProcessItem) {
                if (isset($countingProcessItem['productId'])) {
                    $productId = $countingProcessItem['productId'];
                    if (!isset($productsById[$productId])) {
                        $productsById[$productId] = $this->entityManager->findByPrimaryKey(
                            ProductDefinition::class,
                            $productId,
                            $context,
                        );
                    }

                    /** @var ProductEntity $product */
                    $product = $productsById[$productId];
                    if (!$product) {
                        throw new InvalidArgumentException(sprintf('No product found with id %s', $productId));
                    }
                    $countingProcessItem['productSnapshot'] = [
                        'id' => $product->getId(),
                        'productNumber' => $product->getProductNumber(),
                        'name' => $productNamesByProductId[$product->getId()],
                    ];

                    $stockInStockLocation = null;
                    if (isset($countingProcessPayload['binLocationId'])) {
                        $stockInStockLocation = $stocksInStockLocation->filter(
                            fn(StockEntity $stock) => ($stock->getBinLocationId() === $countingProcessPayload['binLocationId']) && ($stock->getProductId() === $productId),
                        )->first();
                    } else {
                        // Stock in unknown in the warehouse
                        $stockInStockLocation = $stocksInStockLocation->filter(
                            fn(StockEntity $stock) => ($stock->getWarehouseId() === $stocktake->getWarehouseId()) && ($stock->getProductId() === $productId),
                        )->first();
                    }
                    $countingProcessItem['stockInStockLocationSnapshot'] ??= $stockInStockLocation ? $stockInStockLocation->getQuantity() : 0;
                } else {
                    $countingProcessItem['productSnapshot'] = [];
                    $countingProcessItem['stockInStockLocationSnapshot'] = 0;
                }
                unset($countingProcessItem);
            }

            $countingProcessPayload['id'] ??= Uuid::randomHex();
            $countingProcessPayload['number'] ??= $this->numberRangeValueGenerator->getValue(
                StocktakeCountingProcessNumberRange::TECHNICAL_NAME,
                $context,
                null,
            );
        }
    }

    public function completeStocktake(string $stocktakeId, string $userId, Context $context): void
    {
        /** @var ?StocktakeEntity $stocktake */
        $stocktake = $this->entityManager->findByPrimaryKey(StocktakeDefinition::class, $stocktakeId, $context);
        if (!$stocktake) {
            throw StocktakingException::stocktakesNotFound([$stocktakeId]);
        }
        if ($stocktake->getImportExportId()) {
            throw StocktakingException::stocktakeAlreadyCompleted(
                $stocktakeId,
                $stocktake->getTitle(),
                $stocktake->getNumber(),
                $stocktake->getImportExportId(),
            );
        }

        $this->stocktakingStockChangeService->persistStocktakeStockChanges($stocktakeId, $userId, $context);
        $this->entityManager->update(
            StocktakeDefinition::class,
            [
                [
                    'id' => $stocktakeId,
                    'completedAt' => new DateTime(),
                ],
            ],
            $context,
        );
    }

    public function getUncountedProductsInUnknownStockLocation(
        string $stocktakeId,
        Context $context,
    ): array {
        $stocktake = $this->entityManager->findByPrimaryKey(StocktakeDefinition::class, $stocktakeId, $context);
        if (!$stocktake) {
            throw StocktakingException::stocktakesNotFound([$stocktakeId]);
        }

        return $this->connection->fetchFirstColumn(
            'SELECT
                    MAX((LOWER(HEX(stock.`product_id`))))

                FROM `pickware_erp_stock` stock

                INNER JOIN `product` ON
                    `product`.`id` = stock.`product_id` AND
                    `product`.`version_id` = stock.`product_version_id`

                LEFT JOIN `pickware_erp_stocktaking_stocktake_counting_process_item` countingProcessItem ON
                    countingProcessItem.`product_id` = `product`.`id` AND
                    countingProcessItem.`product_version_id` = `product`.`version_id`

                LEFT JOIN `pickware_erp_stocktaking_stocktake_counting_process` countingProcess ON
                    countingProcess.`id` = countingProcessItem.`counting_process_id` AND

                    countingProcess.`stocktake_id` = :stocktakeId AND
                    countingProcess.`bin_location_id` IS NULL AND
                    countingProcess.`bin_location_snapshot` IS NULL

                WHERE
                    stock.`location_type_technical_name` = :locationTypeTechnicalName AND
                    stock.`warehouse_id` = :warehouseId

                GROUP BY stock.`product_id`

                HAVING COUNT(countingProcess.id) = 0',
            [
                'locationTypeTechnicalName' => LocationTypeDefinition::TECHNICAL_NAME_WAREHOUSE,
                'stocktakeId' => hex2bin($stocktakeId),
                'warehouseId' => hex2bin($stocktake->getWarehouseId()),
            ],
        );
    }
}
