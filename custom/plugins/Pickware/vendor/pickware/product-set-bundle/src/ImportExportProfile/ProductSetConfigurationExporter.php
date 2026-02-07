<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\ImportExportProfile;

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
use Pickware\PickwareErpStarter\Translation\Translator;
use Pickware\ProductSetBundle\Model\ProductSetConfigurationDefinition;
use Pickware\ProductSetBundle\Model\ProductSetConfigurationEntity;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductSetConfigurationExporter implements Exporter, FileExporter, HeaderExporter
{
    public const TECHNICAL_NAME = 'product-set-configuration';
    public const COLUMN_PRODUCT_SET_PRODUCT_NUMBER = 'productSet.product.productNumber';
    public const COLUMN_PRODUCT_SET_PRODUCT_NAME = 'productSet.product.name';
    public const COLUMN_PRODUCT_SET_CONFIGURATION_PRODUCT_NUMBER = 'product.productNumber';
    public const COLUMN_PRODUCT_SET_CONFIGURATION_PRODUCT_NAME = 'product.name';
    public const COLUMN_QUANTITY = 'quantity';
    public const COLUMNS = [
        self::COLUMN_PRODUCT_SET_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_SET_PRODUCT_NAME,
        self::COLUMN_PRODUCT_SET_CONFIGURATION_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_SET_CONFIGURATION_PRODUCT_NAME,
        self::COLUMN_QUANTITY,
    ];
    public const DEFAULT_COLUMNS = [
        self::COLUMN_PRODUCT_SET_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_SET_PRODUCT_NAME,
        self::COLUMN_PRODUCT_SET_CONFIGURATION_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_SET_CONFIGURATION_PRODUCT_NAME,
        self::COLUMN_QUANTITY,
    ];
    public const COLUMN_TRANSLATIONS = [
        self::COLUMN_PRODUCT_SET_PRODUCT_NUMBER => 'pickware-product-set.product-set-configuration-export.columns.product-set-product-number',
        self::COLUMN_PRODUCT_SET_PRODUCT_NAME => 'pickware-product-set.product-set-configuration-export.columns.product-set-product-name',
        self::COLUMN_PRODUCT_SET_CONFIGURATION_PRODUCT_NUMBER => 'pickware-product-set.product-set-configuration-export.columns.product-set-configuration-product-number',
        self::COLUMN_PRODUCT_SET_CONFIGURATION_PRODUCT_NAME => 'pickware-product-set.product-set-configuration-export.columns.product-set-configuration-product-name',
        self::COLUMN_QUANTITY => 'pickware-product-set.product-set-configuration-export.columns.quantity',
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Translator $translator,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
        private readonly ProductNameFormatterService $productNameFormatterService,
        private readonly int $batchSize,
    ) {}

    public function exportChunk(string $exportId, int $nextRowNumberToWrite, Context $context): ?int
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $exportConfig = $export->getConfig();
        $columns = $exportConfig['columns'] ?? self::DEFAULT_COLUMNS;

        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $exportConfig['criteria'],
            $this->getEntityDefinitionClassName(),
        );

        // Retrieve the next batch of matching results. Reminder: row number starts with 1.
        $criteria->setLimit($this->batchSize);
        $criteria->setOffset($nextRowNumberToWrite - 1);

        $exportRows = $this->getRowData(
            $criteria,
            $exportConfig['locale'],
            $columns,
            $context,
        );

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

    private function getRowData(
        Criteria $criteria,
        string $locale,
        array $columns,
        Context $context,
    ): array {
        $csvHeaderTranslations = $this->getCsvHeaderTranslations($locale, $context);
        $criteria = EntityManager::sanitizeCriteria($criteria);

        $criteria->addAssociations([
            'productSet.product',
            'product',
        ]);

        $productSetConfigurations = $this->entityManager->findBy(ProductSetConfigurationDefinition::class, $criteria, $context);

        $productNames = $this->productNameFormatterService->getFormattedProductNames(
            [
                ...array_values($productSetConfigurations->map(fn(ProductSetConfigurationEntity $productSetConfiguration) => $productSetConfiguration->getProductSet()->getProductId())),
                ...array_values($productSetConfigurations->map(fn(ProductSetConfigurationEntity $productSetConfiguration) => $productSetConfiguration->getProductId())),
            ],
            [],
            $context,
        );

        $rows = [];
        /** @var ProductSetConfigurationEntity $productSetConfiguration */
        foreach ($productSetConfigurations as $productSetConfiguration) {
            $columnValues = [
                self::COLUMN_PRODUCT_SET_PRODUCT_NUMBER => $productSetConfiguration->getProductSet()->getProduct()->getProductNumber(),
                self::COLUMN_PRODUCT_SET_PRODUCT_NAME => $productNames[$productSetConfiguration->getProductSet()->getProductId()],
                self::COLUMN_PRODUCT_SET_CONFIGURATION_PRODUCT_NUMBER => $productSetConfiguration->getProduct()->getProductNumber(),
                self::COLUMN_PRODUCT_SET_CONFIGURATION_PRODUCT_NAME => $productNames[$productSetConfiguration->getProductId()],
                self::COLUMN_QUANTITY => $productSetConfiguration->getQuantity(),
            ];

            $currentRow = [];
            foreach ($columns as $column) {
                $currentRow[$csvHeaderTranslations[$column]] = $columnValues[$column];
            }
            $rows[] = $currentRow;
        }

        return $rows;
    }

    public function getFileName(string $exportId, Context $context): string
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $this->translator->setTranslationLocale($export->getConfig()['locale'], $context);

        return sprintf(
            $this->translator->translate('pickware-product-set.product-set-configuration-export.file-name'),
            $export->getCreatedAt()->format('Y-m-d H_i_s'),
        );
    }

    public function getEntityDefinitionClassName(): string
    {
        return ProductSetConfigurationDefinition::class;
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

    private function getCsvHeaderTranslations(string $locale, Context $context): array
    {
        $this->translator->setTranslationLocale($locale, $context);

        return array_map(fn($snippedId) => $this->translator->translate($snippedId), self::COLUMN_TRANSLATIONS);
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
