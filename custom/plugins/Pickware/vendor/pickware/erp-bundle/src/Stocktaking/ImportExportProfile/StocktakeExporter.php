<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\ImportExportProfile;

use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\CsvErrorFactory;
use Pickware\PickwareErpStarter\ImportExport\Exporter;
use Pickware\PickwareErpStarter\ImportExport\FileExporter;
use Pickware\PickwareErpStarter\ImportExport\HeaderExporter;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeCountingProcessEntity;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeCountingProcessItemDefinition;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeCountingProcessItemEntity;
use Pickware\PickwareErpStarter\Translation\Translator;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('pickware_erp.import_export.exporter', attributes: ['profileTechnicalName' => 'stocktake'])]
class StocktakeExporter implements Exporter, FileExporter, HeaderExporter
{
    public const TECHNICAL_NAME = 'stocktake';
    public const COLUMN_CREATED_AT = 'createdAt';
    public const COLUMN_COUNTING_PROCESS_NUMBER = 'countingProcess.number';
    public const COLUMN_BIN_LOCATION = 'countingProcess.binLocation.code';
    public const COLUMN_PRODUCT_NUMBER = 'product.productNumber';
    public const COLUMN_PRODUCT_NAME = 'product.name';
    public const COLUMN_PRODUCT_EXPECTED_TOTAL = 'expectedWarehouseStock';
    public const COLUMN_PRODUCT_COUNTED_TOTAL = 'product.extensions.pickwareStocktakeProductSummaries.countedStock';
    public const COLUMN_PRODUCT_DIFFERENCE_TOTAL = 'product.extensions.pickwareStocktakeProductSummaries.absoluteStockDifference';
    public const COLUMN_COUNTED_QUANTITY = 'quantity';
    public const COLUMN_COUNTING_PROCESS_USER = 'countingProcess.user.username';

    // These column names do not "exist" as properties. They are computed names and the values in the UI depend on the
    // state of the stocktake.
    public const COLUMN_STOCK_LOCATION_STOCK = 'computedStockLocationStock';
    public const COLUMN_ABSOLUTE_STOCK_DIFFERENCE = 'computedAbsoluteStockDifference';
    public const COLUMNS = [
        self::COLUMN_CREATED_AT,
        self::COLUMN_COUNTING_PROCESS_NUMBER,
        self::COLUMN_BIN_LOCATION,
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_PRODUCT_EXPECTED_TOTAL,
        self::COLUMN_PRODUCT_COUNTED_TOTAL,
        self::COLUMN_PRODUCT_DIFFERENCE_TOTAL,
        self::COLUMN_STOCK_LOCATION_STOCK,
        self::COLUMN_COUNTED_QUANTITY,
        self::COLUMN_ABSOLUTE_STOCK_DIFFERENCE,
        self::COLUMN_COUNTING_PROCESS_USER,
    ];
    public const COLUMN_TRANSLATIONS = [
        self::COLUMN_CREATED_AT => 'pickware-erp-starter.stocktake-export.columns.created-at',
        self::COLUMN_COUNTING_PROCESS_NUMBER => 'pickware-erp-starter.stocktake-export.columns.counting-process-number',
        self::COLUMN_BIN_LOCATION => 'pickware-erp-starter.stocktake-export.columns.bin-location',
        self::COLUMN_PRODUCT_NUMBER => 'pickware-erp-starter.stocktake-export.columns.product-number',
        self::COLUMN_PRODUCT_NAME => 'pickware-erp-starter.stocktake-export.columns.product-name',
        self::COLUMN_PRODUCT_EXPECTED_TOTAL => 'pickware-erp-starter.stocktake-export.columns.expected-total',
        self::COLUMN_PRODUCT_COUNTED_TOTAL => 'pickware-erp-starter.stocktake-export.columns.counted-total',
        self::COLUMN_PRODUCT_DIFFERENCE_TOTAL => 'pickware-erp-starter.stocktake-export.columns.difference-total',
        self::COLUMN_STOCK_LOCATION_STOCK => 'pickware-erp-starter.stocktake-export.columns.expected',
        self::COLUMN_COUNTED_QUANTITY => 'pickware-erp-starter.stocktake-export.columns.counted',
        self::COLUMN_ABSOLUTE_STOCK_DIFFERENCE => 'pickware-erp-starter.stocktake-export.columns.difference',
        self::COLUMN_COUNTING_PROCESS_USER => 'pickware-erp-starter.stocktake-export.columns.user',
    ];

    private EntityManager $entityManager;
    private CriteriaJsonSerializer $criteriaJsonSerializer;
    private ProductNameFormatterService $productNameFormatterService;
    private Translator $translator;
    private int $batchSize;

    public function __construct(
        EntityManager $entityManager,
        CriteriaJsonSerializer $criteriaJsonSerializer,
        ProductNameFormatterService $productNameFormatterService,
        Translator $translator,
        #[Autowire('%pickware_erp.import_export.profiles.stocktaking.batch_size%')]
        int $batchSize,
    ) {
        $this->entityManager = $entityManager;
        $this->criteriaJsonSerializer = $criteriaJsonSerializer;
        $this->productNameFormatterService = $productNameFormatterService;
        $this->translator = $translator;
        $this->batchSize = $batchSize;
    }

    public function exportChunk(string $exportId, int $nextRowNumberToWrite, Context $context): ?int
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $exportConfig = $export->getConfig();

        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $export->getConfig()['criteria'],
            $this->getEntityDefinitionClassName(),
        );
        $criteria->setLimit($this->batchSize);
        $criteria->setOffset($nextRowNumberToWrite - 1);
        $criteria
            ->addAssociation('snapshotItem')
            ->addAssociation('product')
            ->addAssociation('countingProcess.user')
            ->addAssociation('countingProcess.binLocation');

        $columns = $exportConfig['columns'] ?? self::COLUMNS;

        $exportRows = $this->getStocktakeCountingProcessItemsRows($criteria, $exportConfig['locale'], $columns, $context);

        $exportElementPayloads = [];
        foreach ($exportRows as $index => $exportRow) {
            $exportElementPayloads[] = [
                'id' => Uuid::randomHex(),
                'importExportId' => $exportId,
                'rowNumber' => $nextRowNumberToWrite + $index,
                'rowData' => $exportRow,
            ];
        }

        $this->entityManager->create(
            ImportExportElementDefinition::class,
            $exportElementPayloads,
            $context,
        );
        $nextRowNumberToWrite += $this->batchSize;

        if (count($exportRows) < $this->batchSize) {
            return null;
        }

        return $nextRowNumberToWrite;
    }

    public function getEntityDefinitionClassName(): string
    {
        return StocktakeCountingProcessItemDefinition::class;
    }

    private function getStocktakeCountingProcessItemsRows(Criteria $criteria, string $locale, array $columns, Context $context): array
    {
        $csvHeaderTranslations = $this->getCsvHeaderTranslations($locale, $context);

        $stocktakeCountingProcessItems = $context->enableInheritance(fn(Context $inheritanceContext) => $this->entityManager->findBy(
            StocktakeCountingProcessItemDefinition::class,
            $criteria,
            $inheritanceContext,
        ));

        $productIds = array_values($stocktakeCountingProcessItems->fmap(
            fn(StocktakeCountingProcessItemEntity $stocktakeCountingProcessItem) => $stocktakeCountingProcessItem->getProductId(),
        ));
        $productNames = $this->productNameFormatterService->getFormattedProductNames(
            $productIds,
            [],
            $context,
        );
        $rows = [];
        /** @var StocktakeCountingProcessItemEntity $stocktakeCountingProcessItem */
        foreach ($stocktakeCountingProcessItems as $stocktakeCountingProcessItem) {
            $product = $stocktakeCountingProcessItem->getProduct();
            $snapshotProductNumber = $stocktakeCountingProcessItem->getProductSnapshot()['productNumber'] ?? '';
            $snapshotProductName = $stocktakeCountingProcessItem->getProductSnapshot()['name'] ?? '';
            $columnValues = [
                self::COLUMN_CREATED_AT => $stocktakeCountingProcessItem->getCreatedAt()->format('d/m/Y H:i'),
                self::COLUMN_BIN_LOCATION => $this->getBinLocationLabel($stocktakeCountingProcessItem->getCountingProcess()),
                self::COLUMN_COUNTING_PROCESS_NUMBER => $stocktakeCountingProcessItem->getCountingProcess()->getNumber(),
                self::COLUMN_PRODUCT_NUMBER => $product ? $product->getProductNumber() : $snapshotProductNumber,
                self::COLUMN_PRODUCT_NAME => $product ? $productNames[$product->getId()] : $snapshotProductName,
                self::COLUMN_PRODUCT_EXPECTED_TOTAL => $stocktakeCountingProcessItem->getSnapshotItem()->getWarehouseStock(),
                self::COLUMN_PRODUCT_COUNTED_TOTAL => $stocktakeCountingProcessItem->getSnapshotItem()->getTotalCounted(),
                self::COLUMN_PRODUCT_DIFFERENCE_TOTAL => $stocktakeCountingProcessItem->getSnapshotItem()->getAbsoluteTotalStockDifference(),
                self::COLUMN_STOCK_LOCATION_STOCK => $stocktakeCountingProcessItem->getSnapshotItem()->getStockLocationStock(),
                self::COLUMN_COUNTED_QUANTITY => $stocktakeCountingProcessItem->getSnapshotItem()->getCounted(),
                self::COLUMN_ABSOLUTE_STOCK_DIFFERENCE => $stocktakeCountingProcessItem->getSnapshotItem()->getAbsoluteStockDifference(),
                self::COLUMN_COUNTING_PROCESS_USER => $this->getUserName($stocktakeCountingProcessItem->getCountingProcess()),
            ];
            $currentRow = [];
            foreach ($columns as $column) {
                $currentRow[$csvHeaderTranslations[$column]]
                    = $columnValues[$column];
            }
            $rows[] = $currentRow;
        }

        return $rows;
    }

    private function getCsvHeaderTranslations(string $locale, Context $context): array
    {
        $this->translator->setTranslationLocale($locale, $context);

        return array_map(fn($snippedId) => $this->translator->translate($snippedId), self::COLUMN_TRANSLATIONS);
    }

    private function getBinLocationLabel(StocktakeCountingProcessEntity $countingProcess): string
    {
        if ($countingProcess->getBinLocation()) {
            return $countingProcess->getBinLocation()->getCode();
        }

        if ($countingProcess->getBinLocationSnapshot()) {
            return $countingProcess->getBinLocationSnapshot()['code'];
        }

        return $this->translator->translate('pickware-erp-starter.stocktake-export.unknown-bin-location');
    }

    private function getUserName(StocktakeCountingProcessEntity $countingProcess): string
    {
        $user = $countingProcess->getUser();
        if (!$user) {
            $deletedTranslation = $this->translator->translate('pickware-erp-starter.stocktake-export.deleted');

            return sprintf(
                '%s %s (%s)',
                $countingProcess->getUserSnapshot()['firstName'] ?? '',
                $countingProcess->getUserSnapshot()['lastName'] ?? '',
                $deletedTranslation,
            );
        }

        return sprintf('%s %s', $user->getFirstName(), $user->getLastName());
    }

    public function getFileName(string $exportId, Context $context): string
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $this->translator->setTranslationLocale($export->getConfig()['locale'], $context);

        return sprintf(
            $this->translator->translate('pickware-erp-starter.stocktake-export.file-name'),
            $export->getConfig()['stocktakeNumber'],
            $export->getCreatedAt()->format('Y-m-d H-i-s'),
        );
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        $errors = new JsonApiErrors();
        $columns = $config['columns'] ?? [];

        $invalidColumns = array_diff($columns, self::COLUMNS);
        foreach ($invalidColumns as $invalidColumn) {
            $errors->addError(CsvErrorFactory::invalidColumn($invalidColumn));
        }

        return $errors;
    }

    public function getHeader(string $exportId, Context $context): array
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);

        $headerTranslations = $this->getCsvHeaderTranslations($export->getConfig()['locale'], $context);
        $translatedColumns = array_map(
            fn(string $column) => $headerTranslations[$column],
            $export->getConfig()['columns'] ?? self::COLUMNS,
        );

        return [$translatedColumns];
    }
}
