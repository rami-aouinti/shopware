<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport;

use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Document\DocumentContentsService;
use Pickware\PickwareErpStarter\ImportExport\DependencyInjection\ExporterRegistry;
use Pickware\PickwareErpStarter\ImportExport\DependencyInjection\ImporterRegistry;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImporterServiceDoesNotExistException;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use SplFileInfo;

class ImportExportService
{
    private EntityManager $entityManager;
    private DocumentContentsService $documentContentsService;
    private ImportExportScheduler $importExportScheduler;
    private ImportExportStateService $importExportStateService;
    private ImporterRegistry $importerRegistry;
    private ExporterRegistry $exporterRegistry;

    public function __construct(
        EntityManager $entityManager,
        DocumentContentsService $documentContentsService,
        ImportExportScheduler $importExportScheduler,
        ImportExportStateService $importExportStateService,
        ImporterRegistry $importerRegistry,
        ExporterRegistry $exporterRegistry,
    ) {
        $this->entityManager = $entityManager;
        $this->documentContentsService = $documentContentsService;
        $this->importExportScheduler = $importExportScheduler;
        $this->importExportStateService = $importExportStateService;
        $this->importerRegistry = $importerRegistry;
        $this->exporterRegistry = $exporterRegistry;
    }

    public function importAsync(?SplFileInfo $file, array $options, Context $context): string
    {
        $technicalName = $options['profileTechnicalName'];
        $config = $options['config'] ?? [];

        if (!$this->importerRegistry->hasImporter($technicalName)) {
            throw new ImporterServiceDoesNotExistException($technicalName);
        }

        if ($file !== null) {
            $stream = fopen($file->getPathname(), 'rb');
            $documentId = $this->documentContentsService->saveStreamAsDocument($stream, $context, [
                'documentTypeTechnicalName' => ImportFileDocumentType::TECHNICAL_NAME,
                'mimeType' => $options['mimeType'],
                'fileName' => $options['fileName'] ?? $file->getFilename(),
            ]);
        }

        $config[ImportExportDefinition::READER_TECHNICAL_NAME_CONFIG_KEY] = $options['readerTechnicalName'];

        $importId = Uuid::randomHex();
        $importPayload = [
            'id' => $importId,
            'type' => ImportExportDefinition::TYPE_IMPORT,
            'profileTechnicalName' => $technicalName,
            'state' => ImportExportDefinition::STATE_PENDING,
            'stateData' => [],
            'documentId' => $documentId ?? null,
            'config' => $config,
            'userId' => $options['userId'] ?? null,
            'userComment' => $options['userComment'] ?? null,
        ];
        $this->entityManager->create(ImportExportDefinition::class, [$importPayload], $context);

        $importer = $this->importerRegistry->getImporterByTechnicalName($technicalName);
        $errors = $importer->validateConfig($config);
        if (count($errors) !== 0) {
            $this->importExportStateService->fail($importId, $errors, $context);

            return $importId;
        }

        $this->importExportScheduler->scheduleImport($importId, $context);

        return $importId;
    }

    public function exportAsync(array $options, Context $context): string
    {
        $config = $options['config'] ?? [];
        $config[ImportExportDefinition::WRITER_TECHNICAL_NAME_CONFIG_KEY] = $options['writerTechnicalName'];

        $exportId = Uuid::randomHex();
        $exportPayload = [
            'id' => $exportId,
            'type' => ImportExportDefinition::TYPE_EXPORT,
            'profileTechnicalName' => $options['profileTechnicalName'],
            'config' => $config,
            'state' => ImportExportDefinition::STATE_PENDING,
            'stateData' => [],
            'userId' => $options['userId'] ?? null,
            'userComment' => $options['userComment'] ?? null,
        ];
        $this->entityManager->create(ImportExportDefinition::class, [$exportPayload], $context);

        $exporter = $this->exporterRegistry->getExporterByTechnicalName($options['profileTechnicalName']);
        $errors = $exporter->validateConfig($config);
        if (count($errors) !== 0) {
            $this->importExportStateService->fail($exportId, $errors, $context);

            return $exportId;
        }

        $this->importExportScheduler->scheduleExport($exportId, $context);

        return $exportId;
    }
}
