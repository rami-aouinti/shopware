<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PosAccountingDocument\Controller;

use DateTime;
use DateTimeZone;
use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchCsvWriter;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchExportConfig;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchExporter;
use Pickware\DatevBundle\PosAccountingDocument\PosAccountingDocumentEntityIdChunkCalculator;
use Pickware\DatevBundle\PosAccountingDocument\PosAccountingDocumentExportConfig;
use Pickware\DatevBundle\PosAccountingDocument\PosAccountingDocumentMessage;
use Pickware\DatevBundle\PosAccountingDocument\PosAccountingDocumentRecordCreator;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\ImportExport\ImportExportService;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportLogEntryDefinition;
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
class PosAccountingDocumentController
{
    public function __construct(
        private readonly PosAccountingDocumentEntityIdChunkCalculator $entityIdChunkCalculator,
        private readonly ImportExportService $importExportService,
        private readonly FeatureFlagService $featureFlagService,
        private readonly EntityManager $entityManager,
    ) {}

    #[Route(path: '/api/_action/pickware-datev/export-pos-documents-to-csv', methods: ['POST'])]
    public function exportPosDocumentsToCsv(
        #[JsonParameter(validations: [new Date()])] string $startDate,
        #[JsonParameter(validations: [new Date()])] string $endDate,
        #[JsonParameter] string $salesChannelId,
        #[JsonParameter] string $userComment,
        Context $context,
    ): JsonResponse {
        $usePosDataModelAbstraction = $this->featureFlagService
            ->getFeatureFlags()
            ->getByName('pickware-pos.feature.cash-point-closing-data-model-abstraction-available')
            ?->isActive() ?? false;

        $exportConfig = (new EntryBatchExportConfig(
            entryBatchRecordCreatorTechnicalName: PosAccountingDocumentRecordCreator::TECHNICAL_NAME,
            startDate: new DateTime($startDate . 'T00:00:00.000+00:00', new DateTimeZone('UTC')),
            endDate: new DateTime($endDate . 'T23:59:59.999+00:00', new DateTimeZone('UTC')),
            salesChannelId: $salesChannelId,
        ))->jsonSerialize();

        $entityIdCount = $this->entityIdChunkCalculator->getEntityIdCountForExportConfig($exportConfig, $context);

        $importExportId = $this->importExportService->exportAsync([
            'profileTechnicalName' => EntryBatchExporter::TECHNICAL_NAME,
            'writerTechnicalName' => EntryBatchCsvWriter::TECHNICAL_NAME,
            'config' => array_merge(
                ['totalCount' => $entityIdCount->getTotal()],
                $exportConfig,
                (new PosAccountingDocumentExportConfig(
                    usePosDataModelAbstraction: $usePosDataModelAbstraction,
                    entityIdCount: $entityIdCount,
                ))->jsonSerialize(),
            ),
            'userComment' => $userComment,
            'userId' => ContextExtension::getUserId($context),
        ], $context);

        if (!$usePosDataModelAbstraction) {
            $this->entityManager->create(
                ImportExportLogEntryDefinition::class,
                [
                    PosAccountingDocumentMessage::createPosNotAvailableMessage()->toImportExportLogEntryPayload($importExportId),
                ],
                $context,
            );
        }

        return new JsonResponse(['importExportId' => $importExportId], Response::HTTP_ACCEPTED);
    }
}
