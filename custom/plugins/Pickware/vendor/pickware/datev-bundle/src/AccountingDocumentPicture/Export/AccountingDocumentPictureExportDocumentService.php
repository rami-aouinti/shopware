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

use Shopware\Core\Framework\Context;

class AccountingDocumentPictureExportDocumentService
{
    public function __construct(
        private readonly AccountingDocumentFileService $accountingDocumentFileMappingProvider,
    ) {}

    public function getDocumentDownloadsForDocumentPackage(AccountingDocumentPackagePayload $accountingDocumentPackage, Context $context): array
    {
        $accountingDocumentFileMetadataList = $this->accountingDocumentFileMappingProvider->getAccountingDocumentPackageFileMetadata(
            $accountingDocumentPackage,
            $context,
        );

        return array_merge(
            array_map(
                fn(AccountingDocumentFileMetadata $accountingDocumentFileMetadata) => [
                    'downloadUrl' => $this->getFileDownloadUrl($accountingDocumentFileMetadata),
                    'metadata' => [
                        'documentId' => $accountingDocumentFileMetadata->getDocumentId(),
                        'fileName' => sprintf(
                            '%s.%s',
                            $accountingDocumentFileMetadata->getDocumentFileName(),
                            $accountingDocumentFileMetadata->getDocumentFileExtension(),
                        ),
                    ],
                ],
                $accountingDocumentFileMetadataList,
            ),
        );
    }

    private function getFileDownloadUrl(AccountingDocumentFileMetadata $accountingDocumentFileMetadata): string
    {
        return sprintf(
            '/_action/document/%s/%s',
            $accountingDocumentFileMetadata->getDocumentId(),
            $accountingDocumentFileMetadata->getDeepLinkCode(),
        );
    }
}
