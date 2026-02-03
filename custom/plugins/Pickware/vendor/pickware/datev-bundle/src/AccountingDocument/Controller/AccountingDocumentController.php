<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocument\Controller;

use DateTime;
use DateTimeZone;
use Pickware\DatevBundle\AccountingDocument\AccountingDocumentEntityIdChunkCalculator;
use Pickware\DatevBundle\AccountingDocument\AccountingDocumentExportConfig;
use Pickware\DatevBundle\AccountingDocument\AccountingDocumentRecordCreator;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchCsvWriter;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchExportConfig;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchExporter;
use Pickware\DatevBundle\PickwareDatevBundle;
use Pickware\PickwareErpStarter\ImportExport\ImportExportService;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionDocumentType;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Date;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class AccountingDocumentController
{
    public const EXPORTED_DOCUMENT_TYPES = [
        InvoiceRenderer::TYPE,
        ZugferdRenderer::TYPE,
        ZugferdEmbeddedRenderer::TYPE,
        StornoRenderer::TYPE,
        InvoiceCorrectionDocumentType::TECHNICAL_NAME,
        PickwareDatevBundle::PICKWARE_POS_RECEIPT_DOCUMENT_TYPE_TECHNICAL_NAME,
    ];

    public function __construct(
        private readonly AccountingDocumentEntityIdChunkCalculator $entityIdChunkCalculator,
        private readonly ImportExportService $importExportService,
    ) {}

    #[Route(path: '/api/_action/pickware-datev/export-documents-to-csv', methods: ['POST'])]
    public function exportDocumentsToCsv(
        #[JsonParameter] string $salesChannelId,
        #[JsonParameter(validations: [new Date()])] string $startDate,
        #[JsonParameter(validations: [new Date()])] string $endDate,
        #[JsonParameter] ?string $userComment,
        Context $context,
    ): JsonResponse {
        $exportConfig = array_merge(
            (new EntryBatchExportConfig(
                AccountingDocumentRecordCreator::TECHNICAL_NAME,
                new DateTime($startDate . 'T00:00:00.000+00:00', new DateTimeZone('UTC')),
                new DateTime($endDate . 'T23:59:59.999+00:00', new DateTimeZone('UTC')),
                $salesChannelId,
            ))->jsonSerialize(),
            (new AccountingDocumentExportConfig(self::EXPORTED_DOCUMENT_TYPES))->jsonSerialize(),
        );

        $documentIdCount = $this->entityIdChunkCalculator->getEntityIdCountForExportConfig($exportConfig, $context);

        $importExportId = $this->importExportService->exportAsync([
            'profileTechnicalName' => EntryBatchExporter::TECHNICAL_NAME,
            'writerTechnicalName' => EntryBatchCsvWriter::TECHNICAL_NAME,
            'config' => array_merge(['totalCount' => $documentIdCount->getTotal()], $exportConfig),
            'userComment' => $userComment,
            'userId' => ContextExtension::getUserId($context),
        ], $context);

        return new JsonResponse(['importExportId' => $importExportId], Response::HTTP_ACCEPTED);
    }
}
