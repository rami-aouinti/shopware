<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\EntryBatch\ImportExportProfile;

use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\GenericEXTFCSVExport\EXTFCSVColumnType;
use Pickware\DatevBundle\GenericEXTFCSVExport\EXTFCSVService;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementCollection;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\FileWriter;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\HeaderWriter;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\ImportExportDocumentService;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\ImportExportWriter;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\WritingOffset;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class EntryBatchCsvWriter implements ImportExportWriter, HeaderWriter, FileWriter
{
    public const TECHNICAL_NAME = 'datev-csv';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ImportExportDocumentService $importExportDocumentService,
        private readonly EXTFCSVService $extfCsvService,
        #[Autowire(param: 'pickware_datev.import_export.database_to_datev_csv_writer.batch_size')]
        private readonly int $batchSize,
    ) {}

    public function writeHeader(string $exportId, array $header, Context $context): void
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);

        $documentId = $export->getDocumentId();
        $path = $this->importExportDocumentService->downloadDocumentContentsFromFilesystem($documentId, $context);

        try {
            $resource = fopen($path, 'ab');
            foreach ($header as $headerLine) {
                fwrite($resource, implode(';', $headerLine) . "\n");
            }
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }

        $this->importExportDocumentService->uploadDocumentContentsToFilesystem($documentId, $path, $context);
    }

    public function writeChunk(string $exportId, WritingOffset $offset, Context $context): ?WritingOffset
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(
            ImportExportDefinition::class,
            $exportId,
            $context,
        );

        $criteria = EntityManager::createCriteriaFromArray(['importExportId' => $exportId]);
        $criteria->addFilter(new RangeFilter('rowNumber', [
            RangeFilter::GTE => $offset->getNextRowNumber(),
            RangeFilter::LT => $offset->getNextRowNumber() + $this->batchSize,
        ]));
        $criteria->addSorting(new FieldSorting('rowNumber', FieldSorting::ASCENDING));
        /** @var ImportExportElementCollection $elements */
        $elements = $this->entityManager->findBy(ImportExportElementDefinition::class, $criteria, $context);

        $path = $this->importExportDocumentService->downloadDocumentContentsFromFilesystem($export->getDocumentId(), $context);

        try {
            $resource = fopen($path, 'ab');
            foreach ($elements as $element) {
                $encodedValues = [];
                foreach ($element->getRowData() as $columnLabel => $columnValue) {
                    $encodedValues[] = $this->extfCsvService->encodeDatevType(
                        EntryBatchExporter::COLUMN_LABEL_TYPE_MAPPING[$columnLabel] ?? EXTFCSVColumnType::FreeString,
                        $columnValue,
                    );
                }
                $offset->setNextRowNumber($offset->getNextRowNumber() + 1);
                fwrite($resource, implode(';', $encodedValues) . "\n");
            }
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }

        $this->importExportDocumentService->uploadDocumentContentsToFilesystem($export->getDocumentId(), $path, $context);

        if (count($elements) < $this->batchSize) {
            return null;
        }

        return $offset;
    }

    public function getTechnicalName(): string
    {
        return self::TECHNICAL_NAME;
    }

    public function getSupportedMimetype(): string
    {
        return FileWriter::MIMETYPE_CSV;
    }

    public function getFileExtension(): string
    {
        return EXTFCSVService::FILE_EXTENSION;
    }
}
