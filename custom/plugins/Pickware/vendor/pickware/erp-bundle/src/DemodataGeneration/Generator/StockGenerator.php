<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemodataGeneration\Generator;

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\DemodataGeneration\Patcher\ConfigPatcher;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * This generator generates stock for every warehouse.
 */
#[AutoconfigureTag('shopware.demodata_generator')]
class StockGenerator implements DemodataGeneratorInterface
{
    public const WAREHOUSING_MODE_CHAOTIC_LOCATION = 'chaotic';
    public const WAREHOUSING_MODE_FIXED_LOCATION = 'fixed';
    public const WAREHOUSING_MODE_DEMO_LOCATION = 'demo';
    private const ENTITY_WRITE_BATCH_SIZE = 50;
    private const WAREHOUSING_MODES = [
        self::WAREHOUSING_MODE_CHAOTIC_LOCATION,
        self::WAREHOUSING_MODE_FIXED_LOCATION,
        self::WAREHOUSING_MODE_DEMO_LOCATION,
    ];
    private const MAIN_WAREHOUSE_DEMO_MODE_STOCK_DISTRIBUTION_CONFIG = [
        'productWithStockSubSetFactor' => 1,
        'minNumberOfBinLocationsWithStockPerProduct' => 2,
        'maxNumberOfBinLocationsWithStockPerProduct' => 3,
    ];
    private const DEMO_MODE_STOCK_DISTRIBUTION_CONFIG = [
        'HL' => self::MAIN_WAREHOUSE_DEMO_MODE_STOCK_DISTRIBUTION_CONFIG,
        'MW' => self::MAIN_WAREHOUSE_DEMO_MODE_STOCK_DISTRIBUTION_CONFIG,
        'RL' => [
            'productWithStockSubSetFactor' => 0.1,
            'minNumberOfBinLocationsWithStockPerProduct' => 1,
            'maxNumberOfBinLocationsWithStockPerProduct' => 1,
        ],
        'NL' => [
            'productWithStockSubSetFactor' => 0.9,
            'minNumberOfBinLocationsWithStockPerProduct' => 1,
            'maxNumberOfBinLocationsWithStockPerProduct' => 2,
        ],
        'SD' => [
            'productWithStockSubSetFactor' => 1,
            'minNumberOfBinLocationsWithStockPerProduct' => 1,
            'maxNumberOfBinLocationsWithStockPerProduct' => 2,
        ],
        'default' => [
            'productWithStockSubSetFactor' => 1,
            'minNumberOfBinLocationsWithStockPerProduct' => 1,
            'maxNumberOfBinLocationsWithStockPerProduct' => 1,
        ],
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly StockMovementService $stockMovementService,
    ) {}

    public function getDefinition(): string
    {
        return StockMovementDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $demodataContext, array $options = []): void
    {
        $warehousingMode = $options['warehousing-mode'] ?? self::WAREHOUSING_MODE_FIXED_LOCATION;
        if (!self::isValidWarehousingMode($warehousingMode)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid warehousing-mode passed to method %s',
                __METHOD__,
            ));
        }

        /** @var ProductCollection $products */
        $products = $this->entityManager->findAll(ProductDefinition::class, $demodataContext->getContext());
        $warehouses = $this->entityManager->findBy(
            WarehouseDefinition::class,
            (new Criteria())->addAssociation('binLocations')->addSorting(new FieldSorting('createdAt')),
            $demodataContext->getContext(),
        );
        /** @var WarehouseEntity $warehouse */
        foreach ($warehouses as $warehouse) {
            if ($warehouse->getBinLocations()->count() > 0) {
                // Stock was already created for this warehouse
                continue;
            }
            switch ($warehousingMode) {
                case self::WAREHOUSING_MODE_FIXED_LOCATION:
                    $this->generateStockInFixedMode($warehouse, $products, $demodataContext);
                    break;
                case self::WAREHOUSING_MODE_CHAOTIC_LOCATION:
                    $this->generateStockInChaoticMode($warehouse, $products, $demodataContext);
                    break;
                case self::WAREHOUSING_MODE_DEMO_LOCATION:
                    $this->generateStockInDemoMode($warehouse, $products, $demodataContext);
                    break;
            }
        }
    }

    /**
     * Creates [5..15] bin locations for each product of a subset of the given products that should have stock in the
     * given warehouse. Then puts stock on [n..m] of these bin locations for each of the products with stock with
     * quantity [50..150] each.
     */
    private function generateStockInDemoMode(
        WarehouseEntity $warehouse,
        ProductCollection $products,
        DemodataContext $demodataContext,
    ): void {
        $stockDistributionConfig = self::DEMO_MODE_STOCK_DISTRIBUTION_CONFIG['default'];
        if (isset(self::DEMO_MODE_STOCK_DISTRIBUTION_CONFIG[$warehouse->getCode()])) {
            $stockDistributionConfig = self::DEMO_MODE_STOCK_DISTRIBUTION_CONFIG[$warehouse->getCode()];
        }

        // Only a subset of the given list of products is used to create stock.
        $productsAsArray = $products->getElements();
        shuffle($productsAsArray);
        $productsToCreateStockFor = array_slice(
            $productsAsArray,
            0,
            max((int)($products->count() * $stockDistributionConfig['productWithStockSubSetFactor']), 1),
        );

        $productsToCreateStockForCount = count($productsToCreateStockFor);
        $binLocationsToGenerate = $productsToCreateStockForCount + random_int(5, 15);
        $binLocations = $this->generateBinLocations(
            $warehouse->getId(),
            $binLocationsToGenerate,
            $demodataContext,
        );
        $demodataContext->getConsole()->text(sprintf(
            'Created %d bin locations',
            $binLocationsToGenerate,
        ));
        $binLocationStack = $binLocations->getElements();
        shuffle($binLocationStack);

        $totalStock = 0;
        $totalStockEntriesWritten = 0;
        $productWarehouseConfigurationPayload = [];
        $demodataContext->getConsole()->text(sprintf(
            'Generating stock in DEMO location warehousing mode for warehouse %s for %d products.',
            $warehouse->getName(),
            $productsToCreateStockForCount,
        ));
        $demodataContext->getConsole()->progressStart($productsToCreateStockForCount);
        /** @var ProductEntity $product */
        foreach ($productsToCreateStockFor as $product) {
            // Stock is created on [n..m] of the created bin locations
            $binLocationCountPerProductToCreateStockFor = min(
                random_int(
                    $stockDistributionConfig['minNumberOfBinLocationsWithStockPerProduct'],
                    $stockDistributionConfig['maxNumberOfBinLocationsWithStockPerProduct'],
                ),
                $binLocationsToGenerate,
            );

            $stockMovements = [];
            $binLocationCountStockWasCreatedFor = 0;
            while ($binLocationCountStockWasCreatedFor < $binLocationCountPerProductToCreateStockFor) {
                if (count($binLocationStack) === 0) {
                    $binLocationStack = $binLocations->getElements();
                    shuffle($binLocationStack);
                }
                $nextBinLocationId = array_pop($binLocationStack)->getId();
                // 5er steps in [50..150]
                $quantity = random_int(10, 30) * 5;
                $stockMovements[] = StockMovement::create([
                    'id' => Uuid::randomHex(),
                    'quantity' => $quantity,
                    'productId' => $product->getId(),
                    'source' => StockLocationReference::unknown(),
                    'destination' => StockLocationReference::binLocation($nextBinLocationId),
                    'comment' => $this->getStockMovementComment(),
                ]);

                $binLocationCountStockWasCreatedFor += 1;
                $totalStock += $quantity;
                $totalStockEntriesWritten += 1;
                $defaultBinLocationId = $nextBinLocationId;
            }

            $productWarehouseConfigurationPayload[] = $this->createProductWarehouseConfigurationPayload(
                $warehouse->getId(),
                $product,
                $defaultBinLocationId,
            );

            if (count($productWarehouseConfigurationPayload) >= self::ENTITY_WRITE_BATCH_SIZE) {
                $this->entityManager->upsert(
                    ProductWarehouseConfigurationDefinition::class,
                    $productWarehouseConfigurationPayload,
                    $demodataContext->getContext(),
                );
                $productWarehouseConfigurationPayload = [];
            }

            $this->stockMovementService->moveStock($stockMovements, $demodataContext->getContext());
            $demodataContext->getConsole()->progressAdvance();
        }

        if (count($productWarehouseConfigurationPayload) > 0) {
            $this->entityManager->upsert(
                ProductWarehouseConfigurationDefinition::class,
                $productWarehouseConfigurationPayload,
                $demodataContext->getContext(),
            );
        }

        $demodataContext->getConsole()->progressFinish();
        $demodataContext->getConsole()->text(sprintf(
            'Put total stock of %d into warehouse %s and wrote %d stock movements.',
            $totalStock,
            $warehouse->getName(),
            $totalStockEntriesWritten,
        ));
        $demodataContext->getConsole()->newLine();
    }

    /**
     * Creates a single bin location for every product in the given warehouse and puts stock quantity [500..1500] on it.
     */
    private function generateStockInFixedMode(
        WarehouseEntity $warehouse,
        ProductCollection $products,
        DemodataContext $demodataContext,
    ): void {
        $warehouseId = $warehouse->getId();
        $binLocations = $this->generateBinLocations($warehouseId, $products->count(), $demodataContext);
        $binLocationsIterator = $binLocations->getIterator();

        $totalStock = 0;
        $demodataContext->getConsole()->text(sprintf(
            'Generating stock in FIXED location warehousing mode for warehouse %s for all products.',
            $warehouse->getName(),
        ));
        $demodataContext->getConsole()->progressStart($products->count());

        $productWarehouseConfigurationPayload = [];
        foreach ($products as $product) {
            // 5er steps in [500..1500]
            $quantity = random_int(100, 300) * 5;
            $binLocationId = $binLocationsIterator->current()->getId();
            $stockMovement = StockMovement::create([
                'id' => Uuid::randomHex(),
                'productId' => $product->getId(),
                'quantity' => $quantity,
                'source' => StockLocationReference::unknown(),
                'destination' => StockLocationReference::binLocation($binLocationId),
                'comment' => $this->getStockMovementComment(),
            ]);
            $productWarehouseConfigurationPayload[] = $this->createProductWarehouseConfigurationPayload(
                $warehouse->getId(),
                $product,
                $binLocationId,
            );
            $binLocationsIterator->next();

            if (count($productWarehouseConfigurationPayload) >= self::ENTITY_WRITE_BATCH_SIZE) {
                $this->entityManager->upsert(
                    ProductWarehouseConfigurationDefinition::class,
                    $productWarehouseConfigurationPayload,
                    $demodataContext->getContext(),
                );
                $productWarehouseConfigurationPayload = [];
            }

            $this->stockMovementService->moveStock([$stockMovement], $demodataContext->getContext());
            $demodataContext->getConsole()->progressAdvance();
            $totalStock += $quantity;
        }

        if (count($productWarehouseConfigurationPayload) > 0) {
            $this->entityManager->upsert(
                ProductWarehouseConfigurationDefinition::class,
                $productWarehouseConfigurationPayload,
                $demodataContext->getContext(),
            );
        }

        $demodataContext->getConsole()->progressFinish();
        $demodataContext->getConsole()->text(sprintf(
            'Put total stock of %d into warehouse %s and wrote %d stock movements.',
            $totalStock,
            $warehouse->getName(),
            $products->count(),
        ));
    }

    /**
     * Creates 50 bin locations in the given warehouse and then for each product distributes a total of [1000..2500]
     * randomly on these bin locations with quantity [50..150] each.
     */
    private function generateStockInChaoticMode(
        WarehouseEntity $warehouse,
        ProductCollection $products,
        DemodataContext $demodataContext,
    ): void {
        $warehouseId = $warehouse->getId();
        $binLocations = $this->generateBinLocations($warehouseId, 50, $demodataContext);
        $binLocationStack = $binLocations->getElements();
        shuffle($binLocationStack);

        $totalStock = 0;
        $totalStockEntriesWritten = 0;
        $demodataContext->getConsole()->text(sprintf(
            'Generating stock in CHAOTIC location warehousing mode for warehouse %s for all products.',
            $warehouse->getName(),
        ));
        $demodataContext->getConsole()->progressStart($products->count());
        $productWarehouseConfigurationPayload = [];

        foreach ($products as $product) {
            // 5er steps in [1000..2500]
            $stockToDistribute = random_int(200, 500) * 5;
            $distributedStock = 0;
            $stockMovements = [];
            $defaultBinLocationId = null;
            while ($stockToDistribute > $distributedStock) {
                if (count($binLocationStack) === 0) {
                    $binLocationStack = $binLocations->getElements();
                    shuffle($binLocationStack);
                }
                $nextBinLocationId = array_pop($binLocationStack)->getId();

                // 5er steps in [50..150]
                $stockForNextBinLocation = min(random_int(10, 30) * 5, $stockToDistribute);
                $stockMovements[] = StockMovement::create([
                    'id' => Uuid::randomHex(),
                    'quantity' => $stockForNextBinLocation,
                    'productId' => $product->getId(),
                    'source' => StockLocationReference::unknown(),
                    'destination' => StockLocationReference::binLocation($nextBinLocationId),
                    'comment' => $this->getStockMovementComment(),
                ]);

                $distributedStock += $stockForNextBinLocation;
                $totalStock += $stockForNextBinLocation;
                $totalStockEntriesWritten++;
                $defaultBinLocationId = $nextBinLocationId;
            }
            $productWarehouseConfigurationPayload[] = $this->createProductWarehouseConfigurationPayload(
                $warehouse->getId(),
                $product,
                $defaultBinLocationId,
            );
            if (count($productWarehouseConfigurationPayload) >= self::ENTITY_WRITE_BATCH_SIZE) {
                $this->entityManager->upsert(
                    ProductWarehouseConfigurationDefinition::class,
                    $productWarehouseConfigurationPayload,
                    $demodataContext->getContext(),
                );
                $productWarehouseConfigurationPayload = [];
            }

            $this->stockMovementService->moveStock($stockMovements, $demodataContext->getContext());
            $demodataContext->getConsole()->progressAdvance();
        }

        if (count($productWarehouseConfigurationPayload) > 0) {
            $this->entityManager->upsert(
                ProductWarehouseConfigurationDefinition::class,
                $productWarehouseConfigurationPayload,
                $demodataContext->getContext(),
            );
        }

        $demodataContext->getConsole()->progressFinish();
        $demodataContext->getConsole()->text(sprintf(
            'Put total stock of %d into warehouse %s and wrote %d stock movements.',
            $totalStock,
            $warehouse->getName(),
            $totalStockEntriesWritten,
        ));
    }

    private function generateBinLocations(
        string $warehouseId,
        int $numberOfBinLocationsToBeCreated,
        DemodataContext $demodataContext,
    ): EntityCollection {
        $binLocationIds = [];
        $binLocationPayload = [];
        for ($i = 0; $i < $numberOfBinLocationsToBeCreated; $i++) {
            $binLocationId = Uuid::randomHex();
            $binLocationIds[] = $binLocationId;
            $binLocationPayload[] = [
                'id' => $binLocationId,
                'warehouseId' => $warehouseId,
                'code' => $this->convertDecimalToBinLocationCode($i),
                'position' => null,
            ];

            if (count($binLocationPayload) >= self::ENTITY_WRITE_BATCH_SIZE) {
                $this->entityManager->create(
                    BinLocationDefinition::class,
                    $binLocationPayload,
                    $demodataContext->getContext(),
                );
                $binLocationPayload = [];
            }
        }
        if (count($binLocationPayload) > 0) {
            $this->entityManager->create(
                BinLocationDefinition::class,
                $binLocationPayload,
                $demodataContext->getContext(),
            );
        }

        return $this->entityManager->findBy(
            BinLocationDefinition::class,
            new Criteria($binLocationIds),
            $demodataContext->getContext(),
        );
    }

    /**
     * Converts a decimal number to a bin location code.
     *
     * The method is defined like this:
     *    0 -> A-01-001
     *    1 -> B-01-001
     *  ...
     *    4 -> E-01-001
     *    5 -> A-02-001
     *    6 -> B-02-001
     *  ...
     *   19 -> E-04-001
     *   20 -> A-01-002
     *   21 -> B-01-002
     *  ...
     *   40 -> A-01-003
     *  ...
     */
    private function convertDecimalToBinLocationCode(int $binLocationCodeAsDecimal): string
    {
        $firstPartOptions = range('A', 'E');
        $secondPartOptions = range(1, 4);

        $carry = $binLocationCodeAsDecimal;

        $firstPartIndex = $carry % count($firstPartOptions);
        $carry = ($carry - $firstPartIndex) / count($firstPartOptions);

        $secondPartIndex = $carry % count($secondPartOptions);
        $carry = ($carry - $secondPartIndex) / count($secondPartOptions);

        $lastPartIndex = $carry;

        return sprintf(
            '%s-%02d-%03d',
            $firstPartOptions[$firstPartIndex],
            $secondPartOptions[$secondPartIndex],
            $lastPartIndex + 1,
        );
    }

    private function getStockMovementComment(): string
    {
        return ConfigPatcher::STOCK_MOVEMENT_COMMENTS[array_rand(ConfigPatcher::STOCK_MOVEMENT_COMMENTS)];
    }

    public static function isValidWarehousingMode(string $warehousingMode): bool
    {
        return in_array($warehousingMode, self::WAREHOUSING_MODES, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function createProductWarehouseConfigurationPayload(
        string $warehouseId,
        ProductEntity $product,
        ?string $defaultBinLocationId,
    ): array {
        return [
            'id' => Uuid::randomHex(),
            'warehouseId' => $warehouseId,
            'productId' => $product->getId(),
            'productVersionId' => $product->getVersionId(),
            'defaultBinLocationId' => $defaultBinLocationId,
            'reorderPoint' => 0,
        ];
    }
}
