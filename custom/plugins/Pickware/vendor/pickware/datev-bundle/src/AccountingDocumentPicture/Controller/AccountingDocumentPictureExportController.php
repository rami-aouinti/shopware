<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocumentPicture\Controller;

use Pickware\DatevBundle\AccountingDocumentPicture\Export\AccountingDocumentPackageIndexFileService;
use Pickware\DatevBundle\AccountingDocumentPicture\Export\AccountingDocumentPackagePayload;
use Pickware\DatevBundle\AccountingDocumentPicture\Export\AccountingDocumentPackagePayloadService;
use Pickware\DatevBundle\AccountingDocumentPicture\Export\AccountingDocumentPictureExportDocumentService;
use Pickware\DatevBundle\AccountingDocumentPicture\Export\AccountingDocumentPictureExportErrorLogFileService;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Pickware\ValidationBundle\Annotation\JsonParameterAsArrayOfUuids;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class AccountingDocumentPictureExportController
{
    public function __construct(
        private readonly AccountingDocumentPackagePayloadService $accountingDocumentPackagePayloadService,
        private readonly AccountingDocumentPictureExportDocumentService $accountingDocumentPictureExportDocumentService,
        private readonly AccountingDocumentPackageIndexFileService $accountingDocumentPackageIndexFileService,
        private readonly AccountingDocumentPictureExportErrorLogFileService $accountingDocumentPictureExportErrorLogFileService,
    ) {}

    #[Route(
        path: '/api/_action/pickware-datev/accounting-document-package-payloads/{documentExportId}',
        requirements: ['documentExportId' => '[a-fA-F0-9]{32}'],
        methods: ['GET'],
    )]
    public function accountingDocumentPackagePayloads(
        string $documentExportId,
        Context $context,
    ): Response {
        return new JsonResponse(
            $this->accountingDocumentPackagePayloadService->calculateDocumentPackagePayloads(
                $documentExportId,
                $context,
            ),
        );
    }

    #[Route(
        path: '/api/_action/pickware-datev/get-document-downloads-for-document-package',
        methods: ['POST'],
    )]
    public function getDocumentDownloadsForDocumentPackage(
        #[JsonParameter] AccountingDocumentPackagePayload $accountingDocumentPackage,
        Context $context,
    ): Response {
        return new JsonResponse(
            $this->accountingDocumentPictureExportDocumentService->getDocumentDownloadsForDocumentPackage(
                $accountingDocumentPackage,
                $context,
            ),
        );
    }

    #[Route(
        path: '/api/_action/pickware-datev/get-accounting-document-package-index-file-content',
        methods: ['POST'],
    )]
    public function getAccountingDocumentPackageIndexFileContent(
        #[JsonParameter] AccountingDocumentPackagePayload $accountingDocumentPackage,
        #[JsonParameterAsArrayOfUuids] array $failedDocumentIds,
        Context $context,
    ): Response {
        return new JsonResponse(
            $this->accountingDocumentPackageIndexFileService->getAccountingDocumentPackageIndexFileContent(
                $accountingDocumentPackage,
                $failedDocumentIds,
                $context,
            ),
        );
    }

    #[Route(
        path: '/api/_action/pickware-datev/get-failed-accounting-documents-log',
        methods: ['POST'],
    )]
    public function getFailedAccountingDocumentsLog(
        #[JsonParameterAsUuid] string $documentExportId,
        #[JsonParameter] array $failedDocumentPayloads,
        Context $context,
    ): Response {
        return new JsonResponse(
            [
                'errorLogFileContent' => $this->accountingDocumentPictureExportErrorLogFileService->getFailedAccountingDocumentsLog(
                    $failedDocumentPayloads,
                ),
                'errorLogFileName' => $this->accountingDocumentPictureExportErrorLogFileService->getFailedAccountingDocumentsLogFileName(
                    $documentExportId,
                    $context,
                ),
            ],
        );
    }
}
