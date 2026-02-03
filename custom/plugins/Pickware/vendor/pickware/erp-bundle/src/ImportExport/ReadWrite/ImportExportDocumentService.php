<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\ReadWrite;

use League\Flysystem\FilesystemOperator;
use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Document\Model\DocumentDefinition;
use Pickware\DocumentBundle\Document\Model\DocumentEntity;
use Pickware\PickwareErpStarter\ImportExport\ExportFileDocumentType;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ImportExportDocumentService
{
    private EntityManager $entityManager;
    private FilesystemOperator $fileSystem;

    public function __construct(
        EntityManager $entityManager,
        #[Autowire(service: 'pickware_document_bundle.filesystem.private')]
        FilesystemOperator $fileSystem,
    ) {
        $this->entityManager = $entityManager;
        $this->fileSystem = $fileSystem;
    }

    public function createExportDocument(string $exportId, string $fileName, string $mimeType, Context $context): string
    {
        $documentId = Uuid::randomHex();
        $this->entityManager->update(
            ImportExportDefinition::class,
            [
                [
                    'id' => $exportId,
                    'document' => [
                        'id' => $documentId,
                        'fileSizeInBytes' => 0,
                        'documentTypeTechnicalName' => ExportFileDocumentType::TECHNICAL_NAME,
                        'mimeType' => $mimeType,
                        'fileName' => $fileName,
                        'pathInPrivateFileSystem' => sprintf('/documents/%s', $documentId),
                    ],
                ],
            ],
            $context,
        );

        return $documentId;
    }

    public function uploadDocumentContentsToFilesystem(string $documentId, string $path, Context $context): void
    {
        /** @var DocumentEntity $document */
        $document = $this->entityManager->getByPrimaryKey(DocumentDefinition::class, $documentId, $context);

        $readStream = fopen($path, 'rb');
        $this->fileSystem->writeStream($document->getPathInPrivateFileSystem(), $readStream, [
            // Adding metadata for i.e. Google cloud storage to prohibit caching of the object
            'metadata' => [
                'cacheControl' => 'public, max-age=0',
            ],
        ]);
        if (is_resource($readStream)) {
            fclose($readStream);
        }

        $this->entityManager->update(
            DocumentDefinition::class,
            [
                [
                    'id' => $document->getId(),
                    'fileSizeInBytes' => $this->fileSystem->fileSize($document->getPathInPrivateFileSystem()),
                ],
            ],
            $context,
        );

        unlink($path);
    }

    public function downloadDocumentContentsFromFilesystem(string $documentId, Context $context): string
    {
        /** @var DocumentEntity $document */
        $document = $this->entityManager->getByPrimaryKey(DocumentDefinition::class, $documentId, $context);

        $tempFilePath = tempnam(sys_get_temp_dir(), '');

        if ($this->fileSystem->fileExists($document->getPathInPrivateFileSystem())) {
            $readStream = $this->fileSystem->readStream($document->getPathInPrivateFileSystem());
            $writeStream = fopen($tempFilePath, 'wb');
            stream_copy_to_stream($readStream, $writeStream);
            fclose($writeStream);
        }

        return $tempFilePath;
    }
}
