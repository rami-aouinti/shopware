<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Benchmarking;

use LogicException;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\Test\TestEntityCreation\TestEntityCreator as PickwareErpStarterTestEntityCreator;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\ShopwarePlugins\ShopwareIntegrationTestPlugin\Benchmarking\AbstractBenchmark;
use Pickware\ShopwarePlugins\ShopwareIntegrationTestPlugin\TestEntityCreation\TestEntityCreator;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxDefinition;

class StockMovementBenchmark extends AbstractBenchmark
{
    /**
     * @var string[]
     */
    private array $binLocationIds = [];

    /**
     * @var string[]
     */
    private array $productIds = [];

    private EntityManager $entityManager;
    private TestEntityCreator $testEntityCreator;
    private PickwareErpStarterTestEntityCreator $pickwareErpStarterTestEntityCreator;
    private StockMovementService $stockMovementService;
    private Context $context;
    private string $warehouseId;
    private string $manufacturerId;
    private string $taxId;

    public function __construct(
        EntityManager $entityManager,
        TestEntityCreator $testEntityCreator,
        PickwareErpStarterTestEntityCreator $pickwareErpStarterTestEntityCreator,
        StockMovementService $stockMovementService,
    ) {
        $this->entityManager = $entityManager;
        $this->testEntityCreator = $testEntityCreator;
        $this->pickwareErpStarterTestEntityCreator = $pickwareErpStarterTestEntityCreator;
        $this->stockMovementService = $stockMovementService;
        $this->context = Context::createDefaultContext();
    }

    public function beforeAllRuns(array $benchmarkDimensions): void
    {
        $biggestBatchSize = max($benchmarkDimensions['batchSize']);

        // Create bin location
        $this->warehouseId = $this->pickwareErpStarterTestEntityCreator->createWarehouse($this->context);
        $this->binLocationIds = array_map(fn() => Uuid::randomHex(), array_fill(0, $biggestBatchSize, null));
        $binLocationPayloads = array_map(
            fn(string $binLocationId) => $this->pickwareErpStarterTestEntityCreator->generateBinLocationPayload(
                [
                    'id' => $binLocationId,
                    'warehouseId' => $this->warehouseId,
                ],
            ),
            $this->binLocationIds,
        );
        $this->entityManager->create(BinLocationDefinition::class, $binLocationPayloads, $this->context);

        // Create products
        $this->manufacturerId = $this->testEntityCreator->createProductManufacturer($this->context);
        $this->taxId = $this->testEntityCreator->createTax($this->context);
        $this->productIds = array_map(
            fn() => Uuid::randomHex(),
            array_fill(0, $biggestBatchSize, null),
        );
        $productPayloads = array_map(
            fn(string $productId) => $this->testEntityCreator->generateProductPayload(
                [
                    'id' => $productId,
                    'taxId' => $this->taxId,
                    'manufacturerId' => $this->manufacturerId,
                    'visibilities' => [],
                ],
            ),
            $this->productIds,
        );
        $this->entityManager->create(ProductDefinition::class, $productPayloads, $this->context);
    }

    public function beforeEachRun(array $benchmarkConfiguration): void
    {
        reset($this->productIds);
        reset($this->binLocationIds);
    }

    public function run(array $benchmarkConfiguration): void
    {
        $batchSize = $benchmarkConfiguration['batchSize'];

        $stockMovements = array_fill(0, $batchSize, null);
        $stockMovements = array_map(function() {
            $productId = current($this->productIds);
            if (!$productId) {
                throw new LogicException('Run out of products');
            }
            next($this->productIds);

            $binLocationId = current($this->binLocationIds);
            if (!$binLocationId) {
                throw new LogicException('Run out of bin location');
            }
            next($this->binLocationIds);

            return StockMovement::create([
                'id' => Uuid::randomHex(),
                'productId' => $productId,
                'quantity' => 1,
                'source' => StockLocationReference::unknown(),
                'destination' => StockLocationReference::binLocation($binLocationId),

            ]);
        }, $stockMovements);

        $this->stockMovementService->moveStock(
            $stockMovements,
            $this->context,
        );
    }

    public function afterAllRuns(array $benchmarkDimensions): void
    {
        $this->entityManager->delete(ProductDefinition::class, $this->productIds, $this->context);
        $this->entityManager->delete(TaxDefinition::class, [$this->taxId], $this->context);
        $this->entityManager->delete(ProductManufacturerDefinition::class, [$this->manufacturerId], $this->context);
        $this->entityManager->delete(BinLocationDefinition::class, $this->binLocationIds, $this->context);
        $this->entityManager->delete(WarehouseDefinition::class, [$this->warehouseId], $this->context);
    }
}
