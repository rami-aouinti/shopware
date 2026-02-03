<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\ImportExportProfile\RelativeStockChange;

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Importer;
use Pickware\PickwareErpStarter\ImportExport\ImportExportStateService;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv\Validator;
use Pickware\PickwareErpStarter\Picking\AlphanumericalPickingStrategy;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockImportCsvRowNormalizer;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockImporter;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockImportLocationFinder;
use Pickware\PickwareErpStarter\Stock\ImportExportProfile\StockLimitConfigurationUpdater;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\Stocking\StockingStrategy;
use Pickware\ValidationBundle\JsonValidator;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('pickware_erp.import_export.importer', attributes: ['profileTechnicalName' => 'relative-stock-change'])]
class RelativeStockChangeImporter implements Importer
{
    public const TECHNICAL_NAME = 'relative-stock-change';
    public const VALIDATION_SCHEMA = [
        '$id' => 'pickware-erp--import-export--relative-stock-change-import',
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'productNumber' => [
                'type' => 'string',
            ],
            'binLocationCode' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'defaultBinLocation' => [
                'oneOf' => [
                    ['$ref' => '#/definitions/empty'],
                    ['type' => 'boolean'],
                ],
            ],
            'warehouseCode' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'warehouseName' => [
                'type' => 'string',
                'maxLength' => 255,
            ],
            'change' => [
                'oneOf' => [
                    ['$ref' => '#/definitions/empty'],
                    ['type' => 'integer'],
                ],
            ],
            'reorderPoint' => [
                'type' => 'integer',
            ],
            'targetMaximumQuantity' => [
                'oneOf' => [
                    ['type' => 'integer'],
                    ['type' => 'null'],
                ],
            ],
            // Is only listed because the exporter exports it. Won't be used in the actual import
            'replenishmentQuantity' => [
                'type' => 'string',
            ],
        ],
        'required' => [
            'productNumber',
        ],
        'definitions' => [
            'empty' => [
                'type' => 'string',
                'maxLength' => 0,
            ],
        ],
    ];

    private StockImporter $stockImporter;

    public function __construct(
        EntityManager $entityManager,
        StockMovementService $stockMovementService,
        StockImportCsvRowNormalizer $normalizer,
        StockImportLocationFinder $stockImportLocationFinder,
        ImportExportStateService $importExportStateService,
        AlphanumericalPickingStrategy $pickingStrategy,
        StockingStrategy $stockingStrategy,
        RelativeStockChangeCalculator $relativeStockChangeCalculator,
        StockLimitConfigurationUpdater $stockLimitConfigurationUpdater,
        #[Autowire('%pickware_erp.import_export.profiles.relative_stock_change.batch_size%')]
        int $batchSize,
        JsonValidator $jsonValidator,
    ) {
        $validator = new Validator($normalizer, self::VALIDATION_SCHEMA, $jsonValidator);
        $this->stockImporter = new StockImporter(
            $entityManager,
            $stockMovementService,
            $normalizer,
            $stockImportLocationFinder,
            $importExportStateService,
            $pickingStrategy,
            $stockingStrategy,
            $relativeStockChangeCalculator,
            $validator,
            $stockLimitConfigurationUpdater,
            $batchSize,
        );
    }

    public function canBeParallelized(): bool
    {
        return $this->stockImporter->canBeParallelized();
    }

    public function getBatchSize(): int
    {
        return $this->stockImporter->getBatchSize();
    }

    public function validateHeaderRow(array $headerRow, Context $context): JsonApiErrors
    {
        return $this->stockImporter->validateHeaderRow($headerRow, $context);
    }

    public function importChunk(string $importId, int $nextRowNumberToRead, Context $context): ?int
    {
        return $this->stockImporter->importChunk($importId, $nextRowNumberToRead, $context);
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        return $this->stockImporter->validateConfig($config);
    }
}
