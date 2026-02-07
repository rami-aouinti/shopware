<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockValuation\ImportExportProfile;

use DateTime;
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
use Pickware\PickwareErpStarter\StockValuation\Model\ReportRowDefinition;
use Pickware\PickwareErpStarter\StockValuation\Model\ReportRowEntity;
use Pickware\PickwareErpStarter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('pickware_erp.import_export.exporter', attributes: ['profileTechnicalName' => 'stock-valuation-report'])]
class StockValuationReportExporter implements Exporter, FileExporter, HeaderExporter
{
    public const TECHNICAL_NAME = 'stock-valuation-report';
    public const COLUMN_PRODUCT_NUMBER = 'product.productNumber';
    public const COLUMN_PRODUCT_NAME = 'product.name';
    public const COLUMN_STOCK = 'stock';
    public const COLUMN_TAX_RATE = 'taxRate';
    public const COLUMN_VALUATION_NET = 'valuationNet';
    public const COLUMN_VALUATION_GROSS = 'valuationGross';
    public const COLUMNS = [
        self::COLUMN_PRODUCT_NUMBER,
        self::COLUMN_PRODUCT_NAME,
        self::COLUMN_STOCK,
        self::COLUMN_TAX_RATE,
        self::COLUMN_VALUATION_NET,
        self::COLUMN_VALUATION_GROSS,
    ];
    public const COLUMN_TRANSLATIONS = [
        self::COLUMN_PRODUCT_NAME => 'pickware-erp-starter.stock-valuation-report-export.columns.product-name',
        self::COLUMN_PRODUCT_NUMBER => 'pickware-erp-starter.stock-valuation-report-export.columns.product-number',
        self::COLUMN_STOCK => 'pickware-erp-starter.stock-valuation-report-export.columns.stock',
        self::COLUMN_TAX_RATE => 'pickware-erp-starter.stock-valuation-report-export.columns.tax-rate',
        self::COLUMN_VALUATION_NET => 'pickware-erp-starter.stock-valuation-report-export.columns.valuation-net',
        self::COLUMN_VALUATION_GROSS => 'pickware-erp-starter.stock-valuation-report-export.columns.valuation-gross',
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
        private readonly Translator $translator,
        #[Autowire('%pickware_erp.import_export.profiles.stock_valuation_report.batch_size%')]
        private readonly int $batchSize,
    ) {}

    public function exportChunk(string $exportId, int $nextRowNumberToWrite, Context $context): ?int
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $exportConfig = $export->getConfig();
        $columns = $exportConfig['columns'] ?? self::COLUMNS;

        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $exportConfig['criteria'],
            $this->getEntityDefinitionClassName(),
        );

        // Retrieve the next batch of matching results. Reminder: row number starts with 1.
        $criteria->setLimit($this->batchSize);
        $criteria->setOffset($nextRowNumberToWrite - 1);

        $exportRows = $this->getStockValuationReportExportRows(
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

    public function getEntityDefinitionClassName(): string
    {
        return ReportRowDefinition::class;
    }

    public function getFileName(string $exportId, Context $context): string
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $this->translator->setTranslationLocale($export->getConfig()['locale'], $context);

        return sprintf(
            $this->translator->translate('pickware-erp-starter.stock-valuation-report-export.file-name'),
            (new DateTime())->format('Y-m-d H_i_s'),
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

    /**
     * @param Criteria $criteria Only filters, sorting, limit and offset are respected
     */
    private function getStockValuationReportExportRows(
        Criteria $criteria,
        string $locale,
        array $columns,
        Context $context,
    ): array {
        $csvHeaderTranslations = $this->getCsvHeaderTranslations($locale, $context);

        $reportRowEntities = $context->enableInheritance(fn(Context $inheritanceContext) => $this->entityManager->findBy(
            ReportRowDefinition::class,
            EntityManager::sanitizeCriteria($criteria),
            $inheritanceContext,
            ['product'],
        ));

        $rows = [];
        /** @var ReportRowEntity $reportRowEntity */
        foreach ($reportRowEntities as $reportRowEntity) {
            $columnValues = [
                self::COLUMN_PRODUCT_NAME => $reportRowEntity->getProductSnapshot()['name'],
                self::COLUMN_PRODUCT_NUMBER => $reportRowEntity->getProductSnapshot()['number'],
                self::COLUMN_STOCK => $reportRowEntity->getStock(),
                self::COLUMN_TAX_RATE => round($reportRowEntity->getTaxRate() * 100, 2),
                self::COLUMN_VALUATION_NET => $reportRowEntity->getValuationNet(),
                self::COLUMN_VALUATION_GROSS => $reportRowEntity->getValuationGross(),
            ];

            $currentRow = [];
            foreach ($columns as $column) {
                $currentRow[$csvHeaderTranslations[$column]] = $columnValues[$column];
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
}
