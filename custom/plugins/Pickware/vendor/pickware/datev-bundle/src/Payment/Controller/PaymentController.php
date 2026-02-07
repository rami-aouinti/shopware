<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Payment\Controller;

use DateTime;
use DateTimeZone;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchCsvWriter;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchExportConfig;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchExporter;
use Pickware\DatevBundle\Payment\PaymentEntityIdChunkCalculator;
use Pickware\DatevBundle\Payment\PaymentRecordCreator;
use Pickware\PickwareErpStarter\ImportExport\ImportExportService;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Date;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class PaymentController
{
    public function __construct(
        private readonly PaymentEntityIdChunkCalculator $entityIdChunkCalculator,
        private readonly ImportExportService $importExportService,
    ) {}

    #[Route(path: '/api/_action/pickware-datev/export-payments-to-csv', methods: ['POST'])]
    public function exportPaymentsToCsv(
        #[JsonParameter] string $salesChannelId,
        #[JsonParameter(validations: [new Date()])] string $startDate,
        #[JsonParameter(validations: [new Date()])] string $endDate,
        #[JsonParameter] ?string $userComment,
        Context $context,
    ): JsonResponse {
        $exportConfig = (new EntryBatchExportConfig(
            PaymentRecordCreator::TECHNICAL_NAME,
            new DateTime($startDate . 'T00:00:00.000+00:00', new DateTimeZone('UTC')),
            new DateTime($endDate . 'T23:59:59.999+00:00', new DateTimeZone('UTC')),
            $salesChannelId,
        ))->jsonSerialize();

        $paymentCaptureIdCount = $this->entityIdChunkCalculator->getEntityIdCountForExportConfig($exportConfig, $context);

        $importExportId = $this->importExportService->exportAsync([
            'profileTechnicalName' => EntryBatchExporter::TECHNICAL_NAME,
            'writerTechnicalName' => EntryBatchCsvWriter::TECHNICAL_NAME,
            'config' => array_merge(['totalCount' => $paymentCaptureIdCount->getTotal()], $exportConfig),
            'userComment' => $userComment,
            'userId' => ContextExtension::getUserId($context),
        ], $context);

        return new JsonResponse(['importExportId' => $importExportId], Response::HTTP_ACCEPTED);
    }
}
