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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Exception;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Batch\BatchStockUpdater;
use Pickware\PickwareErpStarter\Batch\Model\BatchDefinition;
use Pickware\PickwareErpStarter\Batch\Model\BatchStockMappingDefinition;
use Pickware\PickwareErpStarter\Product\Model\PickwareProductEntity;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * This generator generates batches for products and assigns existing stock to these batches.
 */
#[AutoconfigureTag('shopware.demodata_generator')]
class BatchGenerator implements DemodataGeneratorInterface
{
    private const BATCH_COMMENTS = [
        'Production batch from supplier',
        'Special handling required',
        'Standard production batch',
        'Batch from certified supplier',
        'Quality assurance passed',
        'Production run completed',
        'Batch ready for distribution',
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Connection $connection,
        private readonly BatchStockUpdater $batchStockUpdater,
    ) {}

    public function getDefinition(): string
    {
        return BatchDefinition::class;
    }

    public function generate(int $numberOfBatches, DemodataContext $demodataContext, array $options = []): void
    {
        $stocks = $this->selectStockForBatches($numberOfBatches, $demodataContext);
        if ($stocks->count() === 0) {
            $demodataContext->getConsole()->text('No stocks available for batch generation');

            return;
        }

        $demodataContext->getConsole()->text(sprintf(
            'Generating batches for %d stocks...',
            $stocks->count(),
        ));
        $demodataContext->getConsole()->progressStart($stocks->count());

        $batchStockMappingPayloads = [];

        /** @var StockEntity $stock */
        foreach ($stocks as $stock) {
            try {
                $batchStockMappingPayloads[] = $this->generateBatchStockMappingPayloadForStock(
                    $stock,
                    $demodataContext,
                );
            } catch (Exception $e) {
                $demodataContext->getConsole()->text(sprintf(
                    'Failed to create batches for stock %s: %s',
                    $stock->getId(),
                    $e->getMessage(),
                ));
            }

            $demodataContext->getConsole()->progressAdvance();
        }

        $this->entityManager->create(
            BatchStockMappingDefinition::class,
            $batchStockMappingPayloads,
            $demodataContext->getContext(),
        );
        $this->batchStockUpdater->calculateBatchStockForProducts(
            $stocks->map(fn(StockEntity $stock) => $stock->getProductId()),
        );

        $demodataContext->getConsole()->progressFinish();
        $demodataContext->getConsole()->text(sprintf(
            'Created %d batches for %d stocks',
            count($batchStockMappingPayloads),
            $stocks->count(),
        ));
    }

    /**
     * @return EntityCollection<StockEntity>
     */
    private function selectStockForBatches(int $numberOfStocks, DemodataContext $demodataContext): EntityCollection
    {
        $stockIds = $this->connection->fetchFirstColumn(
            'SELECT HEX(id) as id FROM pickware_erp_stock ORDER BY RAND() LIMIT :limit',
            ['limit' => $numberOfStocks],
            ['limit' => ParameterType::INTEGER],
        );

        return $this->entityManager->findBy(
            StockDefinition::class,
            ['id' => $stockIds],
            $demodataContext->getContext(),
            ['product.pickwareErpPickwareProduct'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function generateBatchStockMappingPayloadForStock(
        StockEntity $stock,
        DemodataContext $demodataContext,
    ): array {
        $faker = $demodataContext->getFaker();
        $productionDate = $faker->dateTimeBetween(startDate: '-6 months', endDate: 'now');
        $bestBeforeDate = (clone $productionDate)->modify('+30 days');

        $number = "{$productionDate->format('Y-m-d')}-{$faker->randomNumber(nbDigits: 8, strict: true)}";

        /** @var PickwareProductEntity $pickwareProduct */
        $pickwareProduct = $stock->getProduct()->getExtension('pickwareErpPickwareProduct');

        return [
            'batch' => [
                'id' => Uuid::randomHex(),
                'productId' => $stock->getProductId(),
                'productVersionId' => $stock->getProduct()->getVersionId(),
                'number' => $number,
                'productionDate' => $productionDate->format('Y-m-d'),
                'bestBeforeDate' => $bestBeforeDate->format('Y-m-d'),
                'comment' => $this->generateBatchComment(),
            ],
            'stockId' => $stock->getId(),
            'quantity' => $stock->getQuantity(),
            'product' => [
                'id' => $stock->getProductId(),
                'pickwareErpPickwareProduct' => [
                    'id' => $pickwareProduct->getId(),
                    'isBatchManaged' => true,
                ],
            ],
        ];
    }

    private function generateBatchComment(): string
    {
        return self::BATCH_COMMENTS[array_rand(self::BATCH_COMMENTS)];
    }
}
