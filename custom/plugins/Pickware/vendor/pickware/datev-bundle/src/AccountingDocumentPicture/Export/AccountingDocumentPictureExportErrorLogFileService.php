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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;

class AccountingDocumentPictureExportErrorLogFileService
{
    private const ERROR_LOG_FILE_NAME_SUFFIX = '_missing_documents.txt';

    public function __construct(
        private readonly Connection $connection,
        private readonly AccountingDocumentPackagePayloadService $accountingDocumentPackagePayloadService,
    ) {}

    public function getFailedAccountingDocumentsLog(array $failedDocumentPayloads): string
    {
        $failedDocumentIds = array_map(
            fn(array $documentPayload) => $documentPayload['documentId'],
            $failedDocumentPayloads,
        );

        $additionalDocumentInformation = $this->connection->fetchAllAssociative(
            '
            SELECT
            `document`.id AS documentId,
            `order`.order_number AS orderNumber,
            `document`.document_number AS documentNumber,
            `document_type`.technical_name AS documentType,
            accountingDocument.guid AS accountingDocumentGuid
            FROM
                `document`
            INNER JOIN
                `document_type` ON `document`.document_type_id = `document_type`.id
            INNER JOIN
                `pickware_datev_accounting_document_guid` AS accountingDocument ON `document`.id = accountingDocument.document_id
            INNER JOIN
                `order` ON `document`.order_id = `order`.id AND `document`.order_version_id = `order`.version_id
            WHERE
                `document`.id IN (:documentIds)
           ',
            [
                'documentIds' => array_map(
                    'hex2bin',
                    $failedDocumentIds,
                ),
            ],
            [
                'documentIds' => ArrayParameterType::BINARY,
            ],
        );

        $additionalDocumentInformationByDocumentId = array_combine(
            array_map(
                fn(array $additionalDocumentInformation) => bin2hex($additionalDocumentInformation['documentId']),
                $additionalDocumentInformation,
            ),
            $additionalDocumentInformation,
        );

        $accountingDocumentFileErrorLogPayloads = array_map(
            function(array $documentPayload) use ($additionalDocumentInformationByDocumentId) {
                $additionalInformation = $additionalDocumentInformationByDocumentId[$documentPayload['documentId']];

                return new AccountingDocumentFileErrorLogPayload(
                    $additionalInformation['orderNumber'],
                    $additionalInformation['documentNumber'],
                    $additionalInformation['documentType'],
                    $additionalInformation['accountingDocumentGuid'],
                    $documentPayload['error'],
                );
            },
            $failedDocumentPayloads,
        );

        $errorLogFileContent = implode(
            PHP_EOL,
            array_map(
                fn(AccountingDocumentFileErrorLogPayload $errorLogPayload) => $errorLogPayload->toLogEntry(),
                $accountingDocumentFileErrorLogPayloads,
            ),
        );

        return $errorLogFileContent;
    }

    public function getFailedAccountingDocumentsLogFileName(string $documentExportId, Context $context): string
    {
        return $this->accountingDocumentPackagePayloadService
            ->getAccountingDocumentPackageBaseFilename($documentExportId, $context) . self::ERROR_LOG_FILE_NAME_SUFFIX;
    }
}
