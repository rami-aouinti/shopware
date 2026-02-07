<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocumentPicture\Export;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Framework\Context;

class AccountingDocumentFileService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DocumentGenerator $documentGenerator,
    ) {}

    /**
     * @return array<AccountingDocumentFileMapping>
     */
    public function getAccountingDocumentPackageFileMappings(
        AccountingDocumentPackagePayload $accountingDocumentPackage,
        Context $context,
    ): array {
        return array_map(
            fn(array $rawAccountingDocumentFileData) => new AccountingDocumentFileMapping(
                bin2hex($rawAccountingDocumentFileData['documentId']),
                $rawAccountingDocumentFileData['AccountingDocumentGuid'],
                $rawAccountingDocumentFileData['documentFileName'],
                $rawAccountingDocumentFileData['documentFileExtension'],
            ),
            $this->getRawAccountingDocumentPackageFileData($accountingDocumentPackage, $context),
        );
    }

    public function getAccountingDocumentPackageFileMetadata(
        AccountingDocumentPackagePayload $accountingDocumentPackage,
        Context $context,
    ): array {
        return array_map(
            fn(array $rawAccountingDocumentFileData) => new AccountingDocumentFileMetadata(
                bin2hex($rawAccountingDocumentFileData['documentId']),
                $rawAccountingDocumentFileData['documentFileName'],
                $rawAccountingDocumentFileData['documentFileExtension'],
                $rawAccountingDocumentFileData['deepLinkCode'],
                $rawAccountingDocumentFileData['documentPath'],
            ),
            $this->getRawAccountingDocumentPackageFileData($accountingDocumentPackage, $context),
        );
    }

    private function getRawAccountingDocumentPackageFileData(
        AccountingDocumentPackagePayload $accountingDocumentPackage,
        Context $context,
    ): array {
        $rawData = $this->fetchDocumentData($accountingDocumentPackage);

        $documentsWithoutDocumentFiles = array_filter(
            $rawData,
            fn($row) => empty($row['documentMediaFileId']) || empty($row['documentMediaFileFileName']) || empty($row['documentMediaFilePath']),
        );
        if (count($documentsWithoutDocumentFiles) === 0) {
            return $rawData;
        }

        foreach ($documentsWithoutDocumentFiles as $row) {
            // The DocumentGenerator will generate the document if the media file does not exist yet, without creating
            // a new document entity. This is necessary as in Shopware versions < 6.5 the media file was only generated
            // when the document was downloaded.
            $this->documentGenerator->readDocument(bin2hex($row['documentId']), $context);
        }

        // After regenerating the document media files, we fetch the data again to get the updated media file
        // information.
        return $this->fetchDocumentData($accountingDocumentPackage);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function fetchDocumentData(AccountingDocumentPackagePayload $accountingDocumentPackage): array
    {
        return $this->connection->fetchAllAssociative(
            '
                SELECT
                    `document`.id AS documentId,
                    `document`.deep_link_code AS deepLinkCode,
                    documentMediaFile.file_name AS documentFileName,
                    documentMediaFile.file_extension AS documentFileExtension,
                    documentMediaFile.path AS documentPath,
                    accountingDocument.guid AS AccountingDocumentGuid,
                    # Necessary to check if the media file is missing
                    `documentMediaFile`.`id` AS documentMediaFileId,
                    `documentMediaFile`.`file_name` AS documentMediaFileFileName,
                    `documentMediaFile`.`path` AS documentMediaFilePath
                FROM
                    `pickware_erp_import_export` AS importExport
                LEFT JOIN
                    `pickware_datev_import_export_accounting_document_guid_mapping` AS accountingDocumentGuidMapping ON importExport.id = accountingDocumentGuidMapping.import_export_id
                LEFT JOIN
                    `pickware_datev_accounting_document_guid` AS accountingDocument ON accountingDocumentGuidMapping.accounting_document_guid_id = accountingDocument.id
                LEFT JOIN
                    `document` ON accountingDocument.document_id = `document`.id
                LEFT JOIN
                     `media` AS documentMediaFile ON `document`.document_media_file_id = documentMediaFile.id
                WHERE
                    importExport.id = :documentExportId
                ORDER BY `document`.id
                LIMIT :limit
                OFFSET :offset',
            [
                'documentExportId' => hex2bin($accountingDocumentPackage->getDocumentExportId()),
                'limit' => $accountingDocumentPackage->getLimit(),
                'offset' => $accountingDocumentPackage->getOffset(),
            ],
            [
                'documentExportId' => ParameterType::STRING,
                'limit' => ParameterType::INTEGER,
                'offset' => ParameterType::INTEGER,
            ],
        );
    }
}
