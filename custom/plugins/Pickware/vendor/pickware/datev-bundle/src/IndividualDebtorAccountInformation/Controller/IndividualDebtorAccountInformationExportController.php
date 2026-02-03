<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\IndividualDebtorAccountInformation\Controller;

use Pickware\DatevBundle\IndividualDebtorAccountInformation\AccountLabelExport\AccountLabelExportService;
use Pickware\DatevBundle\IndividualDebtorAccountInformation\BaseDataExport\BaseDataExportService;
use Pickware\DatevBundle\IndividualDebtorAccountInformation\ExportedIndividualDebtorService;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class IndividualDebtorAccountInformationExportController
{
    public function __construct(
        private readonly ExportedIndividualDebtorService $exportedIndividualDebtorService,
        private readonly AccountLabelExportService $accountLabelExportService,
        private readonly BaseDataExportService $baseDataExportService,
        private readonly ClockInterface $clock,
    ) {}

    #[Route(
        path: '/api/_action/pickware-datev/get-export-has-individual-debtor-account-information',
        methods: ['POST'],
    )]
    public function getExportHasIndividualDebtorAccountInformation(
        #[JsonParameterAsUuid] string $documentExportId,
        Context $context,
    ): Response {
        return new JsonResponse([
            'hasIndividualDebtorAccountInformation' => $this->exportedIndividualDebtorService->getIndividualDebtorAccountInformationCountForExport(
                $documentExportId,
                $context,
            ) > 0,
        ]);
    }

    #[Route(
        path: '/api/_action/pickware-datev/get-account-label-export',
        methods: ['POST'],
    )]
    public function getAccountLabelExport(
        #[JsonParameterAsUuid] string $documentExportId,
        Context $context,
    ): Response {
        $exportCreatedAt = $this->clock->now();

        return new JsonResponse([
            'fileName' => $this->accountLabelExportService->getFileName(
                $exportCreatedAt,
                $documentExportId,
                $context,
            ),
            'fileContent' => $this->accountLabelExportService->getFileContent(
                $exportCreatedAt,
                $documentExportId,
                $context,
            ),
        ]);
    }

    #[Route(
        path: '/api/_action/pickware-datev/get-base-data-export',
        methods: ['POST'],
    )]
    public function getBaseDataExport(
        #[JsonParameterAsUuid] string $documentExportId,
        Context $context,
    ): Response {
        $exportCreatedAt = $this->clock->now();

        return new JsonResponse([
            'fileName' => $this->baseDataExportService->getFileName(
                $exportCreatedAt,
                $documentExportId,
                $context,
            ),
            'fileContent' => $this->baseDataExportService->getFileContent(
                $exportCreatedAt,
                $documentExportId,
                $context,
            ),
        ]);
    }
}
