<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Warehouse\Import;

use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\CsvErrorFactory;
use Pickware\PickwareErpStarter\ImportExport\Exporter;
use Pickware\PickwareErpStarter\ImportExport\FileExporter;
use Pickware\PickwareErpStarter\ImportExport\HeaderExporter;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\Translation\Translator;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationCollection;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\Subscriber\BinLocationPositionFeatureFlag;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('pickware_erp.import_export.exporter', attributes: ['profileTechnicalName' => 'bin-location'])]
class BinLocationExporter implements Exporter, FileExporter, HeaderExporter
{
    public const TECHNICAL_NAME = 'bin-location';
    public const COLUMN_CODE = 'code';
    public const COLUMN_POSITION = 'position';
    public const COLUMNS = [
        self::COLUMN_CODE,
        self::COLUMN_POSITION,
    ];
    public const COLUMN_TRANSLATIONS = [
        self::COLUMN_CODE => 'pickware-erp-starter.bin-location-export.columns.code',
        self::COLUMN_POSITION => 'pickware-erp-starter.bin-location-export.columns.position',
    ];

    private int $batchSize;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
        private readonly Translator $translator,
        private readonly FeatureFlagService $featureFlagService,
        #[Autowire('%pickware_erp.import_export.profiles.bin_location.batch_size%')]
        int $batchSize,
    ) {
        $this->batchSize = $batchSize;
    }

    public function exportChunk(string $exportId, int $nextRowNumberToWrite, Context $context): ?int
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $exportConfig = $export->getConfig();
        $isBinLocationPositionActive = $this->featureFlagService->isActive(BinLocationPositionFeatureFlag::NAME);

        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $exportConfig['criteria'],
            $this->getEntityDefinitionClassName(),
        );

        // Retrieve the next batch of matching results. Reminder: row number starts with 1.
        $criteria->setLimit($this->batchSize);
        $criteria->setOffset($nextRowNumberToWrite - 1);

        /** @var BinLocationCollection $exportRows */
        $exportRows = array_values($this->entityManager->findBy(
            BinLocationDefinition::class,
            $criteria,
            $context,
        )->getElements());

        $exportElementPayloads = [];
        foreach ($exportRows as $index => $exportRow) {
            $rowData = [
                'code' => $exportRow->getCode(),
                'position' => $exportRow->getPosition(),
            ];
            if (!$isBinLocationPositionActive) {
                unset($rowData['position']);
            }

            $exportElementPayloads[] = [
                'id' => Uuid::randomHex(),
                'importExportId' => $exportId,
                'rowNumber' => $nextRowNumberToWrite + $index,
                'rowData' => $rowData,
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
        return BinLocationDefinition::class;
    }

    public function getFileName(string $exportId, Context $context): string
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->findByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $this->translator->setTranslationLocale($export->getConfig()['locale'], $context);

        return sprintf(
            $this->translator->translate('pickware-erp-starter.bin-location-export.file-name'),
            $export->getCreatedAt()->format('Y-m-d H_i_s'),
        );
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        $errors = new JsonApiErrors();
        $columns = $config['columns'] ?? [];

        foreach ($columns as $invalidColumn) {
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
            $this->getColumns(),
        );

        return [$translatedColumns];
    }

    private function getColumns(): array
    {
        if ($this->featureFlagService->isActive(BinLocationPositionFeatureFlag::NAME)) {
            return self::COLUMNS;
        }

        return array_values(array_filter(
            self::COLUMNS,
            fn(string $column) => $column !== self::COLUMN_POSITION,
        ));
    }

    private function getColumTranslations(): array
    {
        $translations = self::COLUMN_TRANSLATIONS;

        if (!$this->featureFlagService->isActive(BinLocationPositionFeatureFlag::NAME)) {
            unset($translations[self::COLUMN_POSITION]);
        }

        return $translations;
    }

    private function getCsvHeaderTranslations(string $locale, Context $context): array
    {
        $this->translator->setTranslationLocale($locale, $context);

        return array_map(fn($snippedId) => $this->translator->translate($snippedId), $this->getColumTranslations());
    }
}
