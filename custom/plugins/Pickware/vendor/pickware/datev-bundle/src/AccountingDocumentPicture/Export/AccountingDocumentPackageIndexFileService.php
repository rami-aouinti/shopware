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

use DOMDocument;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Shopware\Core\Framework\Context;

class AccountingDocumentPackageIndexFileService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly AccountingDocumentFileService $accountingDocumentFileMappingProvider,
    ) {}

    /**
     * @param array<string> $failedDocumentIds
     */
    public function getAccountingDocumentPackageIndexFileContent(
        AccountingDocumentPackagePayload $accountingDocumentPackage,
        array $failedDocumentIds,
        Context $context,
    ): string {
        /** @var ImportExportEntity $documentExport */
        $documentExport = $this->entityManager->getByPrimaryKey(
            ImportExportDefinition::class,
            $accountingDocumentPackage->getDocumentExportId(),
            $context,
            ['document'],
        );

        $content = $this->getDocumentTemplate();
        $content = str_replace(
            [
                '{CREATION_DATE}',
                '{DESCRIPTION}',
            ],
            [
                $documentExport->getCreatedAt()->format('Y-m-d\\TH:i:s'),
                sprintf(
                    'Belege zum Buchungsstapel "%s"',
                    $documentExport->getDocument()->getFileName(),
                ),
            ],
            $content,
        );

        // Return xml pretty printed
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $doc->loadXML($this->addDocumentsToXml(
            $content,
            $this->accountingDocumentFileMappingProvider->getAccountingDocumentPackageFileMappings(
                $accountingDocumentPackage,
                $context,
            ),
            $failedDocumentIds,
        ));

        return $doc->saveXML();
    }

    /**
     * @param array<AccountingDocumentFileMapping> $accountingDocumentFileMappings
     * @param array<string> $failedDocumentIds
     */
    private function addDocumentsToXml(string $xmlContent, array $accountingDocumentFileMappings, array $failedDocumentIds): string
    {
        $accountingDocumentFileMappings = array_filter(
            $accountingDocumentFileMappings,
            fn(AccountingDocumentFileMapping $accountingDocumentFileMapping) => !in_array(
                $accountingDocumentFileMapping->getDocumentId(),
                $failedDocumentIds,
                true,
            ),
        );
        $nodeTemplate = $this->getNodeTemplate();
        $batchXmlContent = implode(array_map(
            fn(AccountingDocumentFileMapping $accountingDocumentFileMapping) => str_replace(
                [
                    '{GUID}',
                    '{TYPE}',
                    '{FILENAME}',
                ],
                [
                    $accountingDocumentFileMapping->getAccountingDocumentGuid(),
                    '2',
                    sprintf(
                        '%s.%s',
                        $accountingDocumentFileMapping->getDocumentFileName(),
                        $accountingDocumentFileMapping->getDocumentFileExtension(),
                    ),
                ],
                $nodeTemplate,
            ),
            $accountingDocumentFileMappings,
        ));

        return str_replace(
            '{DOCUMENTS}',
            $batchXmlContent,
            $xmlContent,
        );
    }

    private function getDocumentTemplate(): string
    {
        return file_get_contents(__DIR__ . '/XmlTemplates/document.xml');
    }

    private function getNodeTemplate(): string
    {
        return file_get_contents(__DIR__ . '/XmlTemplates/node.xml');
    }
}
