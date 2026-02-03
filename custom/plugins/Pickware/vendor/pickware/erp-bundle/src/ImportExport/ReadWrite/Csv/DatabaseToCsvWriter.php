<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\ReadWrite\Csv;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
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

class DatabaseToCsvWriter implements ImportExportWriter, HeaderWriter, FileWriter
{
    public const TECHNICAL_NAME = 'csv';

    private EntityManager $entityManager;
    private ImportExportDocumentService $importExportDocumentService;
    private int $batchSize;

    public function __construct(
        EntityManager $entityManager,
        ImportExportDocumentService $importExportDocumentService,
        #[Autowire('%pickware_erp.import_export.database_to_csv_writer.batch_size%')]
        int $batchSize,
    ) {
        $this->entityManager = $entityManager;
        $this->importExportDocumentService = $importExportDocumentService;
        $this->batchSize = $batchSize;
    }

    public function writeHeader(string $exportId, array $header, Context $context): void
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);

        $documentId = $export->getDocumentId();
        $path = $this->importExportDocumentService->downloadDocumentContentsFromFilesystem($documentId, $context);

        try {
            $csvWriter = new CsvWriter($path);
            foreach ($header as $headerLine) {
                $csvWriter->append($headerLine);
            }
        } finally {
            if (isset($csvWriter)) {
                $csvWriter->close();
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
        $elements = $this->entityManager->findBy(ImportExportElementDefinition::class, $criteria, $context);

        $path = $this->importExportDocumentService->downloadDocumentContentsFromFilesystem($export->getDocumentId(), $context);

        try {
            $csvWriter = new CsvWriter($path);

            foreach ($elements as $element) {
                $csvWriter->append($element->getRowData());
                $offset->setNextRowNumber($offset->getNextRowNumber() + 1);
            }
        } finally {
            if (isset($csvWriter)) {
                $csvWriter->close();
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
        return '.csv';
    }
}
