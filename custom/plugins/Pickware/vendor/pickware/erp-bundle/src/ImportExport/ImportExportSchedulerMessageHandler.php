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

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\DependencyInjection\ExporterRegistry;
use Pickware\PickwareErpStarter\ImportExport\DependencyInjection\ImporterRegistry;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportException;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportExportException;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\DependencyInjection\ImportExportReaderRegistry;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\DependencyInjection\ImportExportWriterRegistry;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\FileReader;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\FileWriter;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\HeaderReader;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\HeaderWriter;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\ImportExportDocumentService;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\ReadingOffset;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\WritingOffset;
use Throwable;

class ImportExportSchedulerMessageHandler
{
    public function __construct(
        private readonly ImportExportStateService $importExportStateService,
        private readonly EntityManager $entityManager,
        private readonly Connection $db,
        private readonly ImporterRegistry $importerRegistry,
        private readonly ExporterRegistry $exporterRegistry,
        private readonly ImportExportReaderRegistry $importExportReaderRegistry,
        private readonly ImportExportWriterRegistry $importExportWriterRegistry,
        private readonly ImportExportDocumentService $importExportDocumentService,
        private readonly ImportExportSchedulerMessageGenerator $messageGenerator,
    ) {}

    /**
     * @return array<ImportExportSchedulerMessage>
     */
    public function handleFileValidationMessage(ImportExportSchedulerMessage $message): array
    {
        $this->importExportStateService->validate($message->getImportExportId(), $message->getContext());
        $import = $this->getImportExportFromMessage($message, ['document']);

        $errors = JsonApiErrors::noError();
        if (!($import->getConfig()[ImportExportDefinition::READER_TECHNICAL_NAME_CONFIG_KEY] ?? null)) {
            $errors->addErrors(ImportExportException::createReaderTechnicalNameNotSetError());
        }

        if (count($errors) !== 0) {
            $this->importExportStateService->fail($message->getImportExportId(), $errors, $message->getContext());

            return [];
        }

        $importExportReader = $this->importExportReaderRegistry->getImportExportReaderByTechnicalName(
            $import->getConfig()[ImportExportDefinition::READER_TECHNICAL_NAME_CONFIG_KEY],
        );

        if ($importExportReader instanceof HeaderReader) {
            $header = $importExportReader->getHeader($import->getId(), $message->getContext());

            $errors->addErrors(
                ...$this->importerRegistry
                ->getImporterByTechnicalName($import->getProfileTechnicalName())
                ->validateHeaderRow($header, $message->getContext())
                ->getErrors(),
            );
        }

        if ($importExportReader instanceof FileReader) {
            if ($import->getDocument() === null) {
                $errors->addErrors(ImportExportException::createFileReaderWithoutDocumentError(
                    $importExportReader->getTechnicalName(),
                ));
            } elseif ($import->getDocument()->getMimeType() !== $importExportReader->getSupportedMimetype()) {
                $errors->addErrors(ImportExportException::createMimetypeMismatchError(
                    $importExportReader->getSupportedMimetype(),
                    $import->getDocument()->getMimeType(),
                ));
            }
        } else {
            if ($import->getDocument() !== null) {
                $errors->addErrors(ImportExportException::createDocumentWithoutFileReaderError(
                    $importExportReader->getTechnicalName(),
                ));
            }
        }

        if (count($errors) !== 0) {
            $this->importExportStateService->fail($message->getImportExportId(), $errors, $message->getContext());

            return [];
        }

        return [
            new ImportExportSchedulerMessage(
                $message->getImportExportId(),
                ImportExportSchedulerMessage::STATE_READ_FILE_TO_DATABASE,
                $message->getContext(),
            ),
        ];
    }

    /**
     * @return array<ImportExportSchedulerMessage>
     */
    public function handleReadFileToDatabaseMessage(ImportExportSchedulerMessage $message): array
    {
        $import = $this->getImportExportFromMessage($message, ['document']);
        if ($import->getStateData() === []) {
            // The first row number that is written to the db is 1 because the header row will not be written. But it
            // must be read and therefore the nextByteToRead starts with 0.
            $initialStateData = new ReadingOffset(1, 0);
            $this->importExportStateService->readFile(
                $message->getImportExportId(),
                $initialStateData,
                $message->getContext(),
            );
            $import->setStateData($initialStateData->jsonSerialize());
        }

        $newOffset = $this->importExportReaderRegistry
            ->getImportExportReaderByTechnicalName($import->getConfig()[ImportExportDefinition::READER_TECHNICAL_NAME_CONFIG_KEY])
            ->readChunk(
                $message->getImportExportId(),
                ReadingOffset::fromArray($import->getStateData()),
                $message->getContext(),
            );
        // When no new offset was returned, we consider the reading to be finished.
        if ($newOffset === null) {
            $this->importExportStateService->resetStateData($message->getImportExportId(), $message->getContext());

            $rowCount = $this->getImportExportRowCount($message->getImportExportId());
            $this->importExportStateService->startRun(
                $message->getImportExportId(),
                $rowCount,
                [],
                $message->getContext(),
            );

            return $this->messageGenerator->createExecuteImportMessagesForImportExport($message->getImportExportId(), $message->getContext());
        }
        // Otherwise, the import is progressed with the new offset
        $this->importExportStateService->readFile(
            $message->getImportExportId(),
            $newOffset,
            $message->getContext(),
        );

        return [
            new ImportExportSchedulerMessage(
                $message->getImportExportId(),
                ImportExportSchedulerMessage::STATE_READ_FILE_TO_DATABASE,
                $message->getContext(),
            ),
        ];
    }

    /**
     * @return array<ImportExportSchedulerMessage>
     */
    public function handleExecuteImportMessage(ImportExportSchedulerMessage $message): array
    {
        $import = $this->getImportExportFromMessage($message);
        $importer = $this->importerRegistry->getImporterByTechnicalName($import->getProfileTechnicalName());
        $nextRowNumberToRead = $message->getNextRowNumberToRead();
        try {
            $newNextRowNumberToRead = $importer->importChunk(
                $import->getId(),
                $nextRowNumberToRead,
                $message->getContext(),
            );

            // If importChunk returned null, it read all rows until the end
            $rowsProcessed = $newNextRowNumberToRead !== null ? $newNextRowNumberToRead - $nextRowNumberToRead : $this->getImportExportRowCount($message->getImportExportId()) - $nextRowNumberToRead + 1;
        } catch (ImportException $exception) {
            $this->importExportStateService->fail(
                $import->getId(),
                new JsonApiErrors([$exception->getJsonApiError()]),
                $message->getContext(),
            );

            return [];
        } catch (Throwable $exception) {
            $this->importExportStateService->fail(
                $import->getId(),
                new JsonApiErrors([
                    ImportException::unknownError($exception, $nextRowNumberToRead)->getJsonApiError(),
                ]),
                $message->getContext(),
            );

            return [];
        }

        $currentProgress = $this->importExportStateService->incrementRunProgress(
            $import->getId(),
            $rowsProcessed,
            [],
            $message->getContext(),
        );

        $importCompleted = $currentProgress >= $this->getImportExportRowCount($message->getImportExportId());
        if ($importCompleted) {
            $this->importExportStateService->finish($import->getId(), $message->getContext());

            return [];
        }

        return $message->getSpawnNextMessage() ? [
            new ImportExportSchedulerMessage(
                $message->getImportExportId(),
                ImportExportSchedulerMessage::STATE_EXECUTE_IMPORT,
                $message->getContext(),
                $newNextRowNumberToRead,
                $message->getSpawnNextMessage(),
            ),
        ] : [];
    }

    /**
     * @return array<ImportExportSchedulerMessage>
     */
    public function handleExecuteExportMessage(ImportExportSchedulerMessage $message): array
    {
        $export = $this->getImportExportFromMessage($message);
        if ($export->getStateData() === []) {
            $initialStateData = ['nextRowNumberToWriteToDatabase' => 1];
            $this->importExportStateService->startRun(
                $message->getImportExportId(),
                $export->getConfig()['totalCount'],
                $initialStateData,
                $message->getContext(),
            );
            $export->setStateData($initialStateData);
        }

        $exporter = $this->exporterRegistry->getExporterByTechnicalName($export->getProfileTechnicalName());
        $newNextRowNumberToWrite = $exporter->exportChunk(
            $export->getId(),
            $export->getStateData()['nextRowNumberToWriteToDatabase'],
            $message->getContext(),
        );

        if ($newNextRowNumberToWrite === null) {
            $this->importExportStateService->resetStateData($export->getId(), $message->getContext());

            // When no new row number was returned, we consider this export to be finished. Continue with writing the
            // csv file.
            return [
                new ImportExportSchedulerMessage(
                    $message->getImportExportId(),
                    ImportExportSchedulerMessage::STATE_WRITE_DATABASE_TO_FILE,
                    $message->getContext(),
                ),
            ];
        }
        // Otherwise, the export is progressed up until (not including) the new next row number
        $this->importExportStateService->setRunProgress(
            $export->getId(),
            $newNextRowNumberToWrite - 1,
            ['nextRowNumberToWriteToDatabase' => $newNextRowNumberToWrite],
            $message->getContext(),
        );

        return [
            new ImportExportSchedulerMessage(
                $message->getImportExportId(),
                ImportExportSchedulerMessage::STATE_EXECUTE_EXPORT,
                $message->getContext(),
            ),
        ];
    }

    /**
     * @return array<ImportExportSchedulerMessage>
     */
    public function handleWriteDatabaseToFileMessage(ImportExportSchedulerMessage $message): array
    {
        $export = $this->getImportExportFromMessage($message);

        if (!($export->getConfig()[ImportExportDefinition::WRITER_TECHNICAL_NAME_CONFIG_KEY] ?? null)) {
            $this->importExportStateService->fail(
                $message->getImportExportId(),
                new JsonApiErrors([ImportExportException::createWriterTechnicalNameNotSetError()]),
                $message->getContext(),
            );

            return [];
        }

        $importExportWriter = $this->importExportWriterRegistry->getImportExportWriterByTechnicalName(
            $export->getConfig()[ImportExportDefinition::WRITER_TECHNICAL_NAME_CONFIG_KEY],
        );

        $exporter = $this->exporterRegistry->getExporterByTechnicalName($export->getProfileTechnicalName());
        if ($importExportWriter instanceof FileWriter && $export->getDocumentId() === null) {
            if ($exporter instanceof FileExporter) {
                $fileName = $exporter->getFileName($export->getId(), $message->getContext());

                $this->importExportDocumentService->createExportDocument(
                    $export->getId(),
                    $fileName . $importExportWriter->getFileExtension(),
                    $importExportWriter->getSupportedMimetype(),
                    $message->getContext(),
                );
            } else {
                $this->importExportStateService->fail(
                    $message->getImportExportId(),
                    new JsonApiErrors([
                        ImportExportException::createFileWriterWithoutFileExporterError(
                            $export->getConfig()[ImportExportDefinition::WRITER_TECHNICAL_NAME_CONFIG_KEY],
                            $export->getProfileTechnicalName(),
                        ),
                    ]),
                    $message->getContext(),
                );

                return [];
            }
        }

        if ($export->getStateData() === []) {
            if ($importExportWriter instanceof HeaderWriter) {
                if (!($exporter instanceof HeaderExporter)) {
                    $this->importExportStateService->fail(
                        $message->getImportExportId(),
                        new JsonApiErrors([
                            ImportExportException::createHeaderWriterWithoutHeaderExporterError(
                                $export->getConfig()[ImportExportDefinition::WRITER_TECHNICAL_NAME_CONFIG_KEY],
                                $export->getProfileTechnicalName(),
                            ),
                        ]),
                        $message->getContext(),
                    );

                    return [];
                }

                $header = $exporter->getHeader($export->getId(), $message->getContext());
                $importExportWriter->writeHeader($export->getId(), $header, $message->getContext());
            }

            $initialWritingOffset = new WritingOffset(1);
            $rowCount = $this->getImportExportRowCount($message->getImportExportId());
            $this->importExportStateService->writeFile(
                $message->getImportExportId(),
                0,
                $rowCount,
                $initialWritingOffset,
                $message->getContext(),
            );
            $export->setStateData($initialWritingOffset->jsonSerialize());
        }

        $newWritingOffset = $importExportWriter->writeChunk(
            $message->getImportExportId(),
            WritingOffset::fromArray($export->getStateData()),
            $message->getContext(),
        );

        if ($newWritingOffset === null) {
            // When no new writing offset was returned, we consider this export to be finished
            $this->importExportStateService->finish($message->getImportExportId(), $message->getContext());

            return [];
        }
        // Otherwise, the export is progressed with the new row number
        $this->importExportStateService->writeFile(
            $message->getImportExportId(),
            $newWritingOffset->getNextRowNumber() - 1,
            $export->getConfig()['totalCount'],
            $newWritingOffset,
            $message->getContext(),
        );

        return [
            new ImportExportSchedulerMessage(
                $message->getImportExportId(),
                ImportExportSchedulerMessage::STATE_WRITE_DATABASE_TO_FILE,
                $message->getContext(),
            ),
        ];
    }

    private function getImportExportFromMessage(
        ImportExportSchedulerMessage $message,
        array $associations = [],
    ): ImportExportEntity {
        return $this->entityManager->getByPrimaryKey(
            ImportExportDefinition::class,
            $message->getImportExportId(),
            $message->getContext(),
            $associations,
        );
    }

    private function getImportExportRowCount(string $importExportId): int
    {
        return (int) $this->db->fetchOne(
            'SELECT COUNT(`id`)
            FROM `pickware_erp_import_export_element`
            WHERE `import_export_id` = :importExportId',
            ['importExportId' => hex2bin($importExportId)],
        );
    }
}
