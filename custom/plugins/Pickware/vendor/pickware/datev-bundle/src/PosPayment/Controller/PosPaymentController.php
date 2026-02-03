<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PosPayment\Controller;

use DateTime;
use DateTimeZone;
use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchCsvWriter;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchExportConfig;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchExporter;
use Pickware\DatevBundle\PosPayment\PosPaymentEntityIdChunkCalculator;
use Pickware\DatevBundle\PosPayment\PosPaymentExportConfig;
use Pickware\DatevBundle\PosPayment\PosPaymentMessage;
use Pickware\DatevBundle\PosPayment\PosPaymentRecordCreator;
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
class PosPaymentController
{
    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
        private readonly PosPaymentEntityIdChunkCalculator $entityIdChunkCalculator,
        private readonly ImportExportService $importExportService,
        private readonly EntityManager $entityManager,
    ) {}

    #[Route(path: '/api/_action/pickware-datev/export-pos-payments-to-csv', methods: ['POST'])]
    public function exportPosPaymentsToCsv(
        #[JsonParameter(validations: [new Date()])] string $startDate,
        #[JsonParameter(validations: [new Date()])] string $endDate,
        #[JsonParameter] string $salesChannelId,
        #[JsonParameter] ?string $userComment,
        Context $context,
    ): JsonResponse {
        $usePosDataModelAbstraction = $this->featureFlagService
            ->getFeatureFlags()
            ->getByName('pickware-pos.feature.cash-point-closing-data-model-abstraction-available')
            ?->isActive() ?? false;

        $config = array_merge(
            (new EntryBatchExportConfig(
                entryBatchRecordCreatorTechnicalName: PosPaymentRecordCreator::TECHNICAL_NAME,
                startDate: new DateTime($startDate . 'T00:00:00.000+00:00', new DateTimeZone('UTC')),
                endDate: new DateTime($endDate . 'T23:59:59.999+00:00', new DateTimeZone('UTC')),
                salesChannelId: $salesChannelId,
            ))->jsonSerialize(),
            (new PosPaymentExportConfig(
                usePosDataModelAbstraction: $usePosDataModelAbstraction,
                entityIdCount: null,
            ))->jsonSerialize(),
        );

        $posPaymentEntityIdCount = $this->entityIdChunkCalculator->getEntityIdCountForExportConfig($config, $context);

        $importExportId = $this->importExportService->exportAsync([
            'profileTechnicalName' => EntryBatchExporter::TECHNICAL_NAME,
            'writerTechnicalName' => EntryBatchCsvWriter::TECHNICAL_NAME,
            'config' => array_merge(
                ['totalCount' => $posPaymentEntityIdCount->getTotal()],
                $config,
                (new PosPaymentExportConfig(
                    usePosDataModelAbstraction: $usePosDataModelAbstraction,
                    entityIdCount: $posPaymentEntityIdCount,
                ))->jsonSerialize(),
            ),
            'userComment' => $userComment,
            'userId' => ContextExtension::getUserId($context),
        ], $context);

        if (!$usePosDataModelAbstraction) {
            $this->entityManager->create(
                ImportExportLogEntryDefinition::class,
                [
                    PosPaymentMessage::createPosNotAvailableMessage()->toImportExportLogEntryPayload($importExportId),
                ],
                $context,
            );
        }

        return new JsonResponse(['importExportId' => $importExportId], Response::HTTP_ACCEPTED);
    }
}
