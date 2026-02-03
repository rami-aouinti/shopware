<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\ReadWrite\Jsonl;

use DateTime;
use League\Flysystem\FilesystemOperator;
use Pickware\DalBundle\DatabaseBulkInsertService;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\FileReader;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\ImportExportReader;
use Pickware\PickwareErpStarter\ImportExport\ReadWrite\ReadingOffset;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class JsonlToDatabaseReader implements ImportExportReader, FileReader
{
    public const TECHNICAL_NAME = 'jsonl';
    public const MAX_HTML_LENGTH = 50000;

    public function __construct(
        private readonly EntityManager $entityManager,
        #[Autowire(service: 'pickware_document_bundle.filesystem.private')]
        private readonly FilesystemOperator $documentBundleFileSystem,
        private readonly DatabaseBulkInsertService $databaseBulkInsertService,
        #[Autowire('%pickware_erp.import_export.jsonl_to_database_reader.batch_size%')]
        private readonly int $batchSize,
    ) {}

    public function readChunk(string $importId, ReadingOffset $offset, Context $context): ?ReadingOffset
    {
        /** @var ImportExportEntity $import */
        $import = $this->entityManager->findByPrimaryKey(
            ImportExportDefinition::class,
            $importId,
            $context,
            ['document'],
        );

        $jsonlReader = new JsonlReader();
        $jsonlStream = $this->documentBundleFileSystem->readStream($import->getDocument()->getPathInPrivateFileSystem());

        $payload = [];
        foreach ($jsonlReader->read($jsonlStream, $offset->getNextByteToRead()) as $rowData) {
            if (is_array($rowData)) {
                $rowData = $this->sanitizeDescriptionHtml($rowData);
            }

            $payload[] = [
                'id' => Uuid::randomBytes(),
                'import_export_id' => hex2bin($import->getId()),
                'row_number' => $offset->getNextRowNumberToWrite(),
                'row_data' => Json::stringify($rowData),
                'created_at' => (new DateTime())->format('Y-m-d H:i:s.u'),
            ];
            $offset->setNextRowNumberToWrite($offset->getNextRowNumberToWrite() + 1);
            if (count($payload) >= $this->batchSize) {
                break;
            }
        }
        $this->databaseBulkInsertService->insert('pickware_erp_import_export_element', $payload);

        $offset->setNextByteToRead(ftell($jsonlStream));
        if (feof($jsonlStream)) {
            return null;
        }

        return $offset;
    }

    /**
     * Fix for oversized HTML descriptions causing database issues (See issue: https://github.com/pickware/shopware-plugins/issues/8769)
     * @param array<mixed> $rowData
     * @return array<mixed>
     */
    private function sanitizeDescriptionHtml(array $rowData): array
    {
        if (isset($rowData['descriptionHtml']) && is_string($rowData['descriptionHtml'])) {
            $textOnly = $this->stripHtmlComments($rowData['descriptionHtml']);
            $rowData['descriptionHtml'] = mb_substr($textOnly, 0, self::MAX_HTML_LENGTH);
        }

        return $rowData;
    }

    private function stripHtmlComments(string $html): string
    {
        return preg_replace('#<!--.*?-->#si', '', $html) ?: $html;
    }

    public function getSupportedMimetype(): string
    {
        return FileReader::MIMETYPE_JSONL;
    }

    public function getTechnicalName(): string
    {
        return self::TECHNICAL_NAME;
    }
}
