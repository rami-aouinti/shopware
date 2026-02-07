<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockValuation\Controller;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\StockValuation\Model\ReportDefinition;
use Pickware\PickwareErpStarter\StockValuation\Model\ReportEntity;
use Pickware\PickwareErpStarter\StockValuation\StockValuationException;
use Pickware\PickwareErpStarter\StockValuation\StockValuationService;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Pickware\ValidationBundle\Annotation\JsonParameterAsArrayOfUuids;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class StockValuationController
{
    public function __construct(
        private readonly StockValuationService $stockValuationService,
        private readonly EntityManager $entityManager,
    ) {}

    #[Route('/api/_action/pickware-erp/create-stock-valuation-report', methods: 'PUT')]
    #[JsonValidation(schemaFilePath: 'create-stock-valuation-report.schema.json')]
    public function createReport(#[JsonParameter] array $report, Context $context): Response
    {
        try {
            $this->stockValuationService->createReport($report, $context);
        } catch (StockValuationException $e) {
            return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(status: Response::HTTP_BAD_REQUEST);
        }

        return new Response('', status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/_action/pickware-erp/perform-next-stock-valuation-report-generation-step', methods: 'PUT')]
    public function performNextReportGenerationStep(#[JsonParameterAsUuid] string $reportId, Context $context): Response
    {
        set_time_limit(600);

        try {
            $this->stockValuationService->performNextReportGenerationStep($reportId, $context);
        } catch (StockValuationException $e) {
            return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(status: Response::HTTP_BAD_REQUEST);
        }

        /** @var ReportEntity $report */
        $report = $this->entityManager->getByPrimaryKey(ReportDefinition::class, $reportId, $context);

        return new JsonResponse(
            [
                'generationStep' => $report->getGenerationStep()->value,
                'progress' => round($report->getGenerationStep()->getProgress() * 100) / 100,
                'generated' => $report->isGenerated(),
            ],
            status: Response::HTTP_OK,
        );
    }

    #[Route('/api/_action/pickware-erp/persist-stock-valuation-report', methods: 'PUT')]
    public function persistReport(#[JsonParameterAsUuid] string $reportId, Context $context): Response
    {
        try {
            $this->stockValuationService->persistReport($reportId, $context);
        } catch (StockValuationException $e) {
            return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(status: Response::HTTP_BAD_REQUEST);
        }

        return new Response('', status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/_action/pickware-erp/get-deletable-reports', methods: 'POST')]
    public function getDeletableReports(#[JsonParameterAsArrayOfUuids] array $warehouseIds): Response
    {
        $reportIds = $this->stockValuationService->getDeletableReportIdsInWarehouses($warehouseIds);

        return new JsonResponse($reportIds, status: Response::HTTP_OK);
    }

    #[Route('/api/_action/pickware-erp/delete-stock-valuation-report', methods: 'PUT')]
    public function deleteReport(#[JsonParameterAsUuid] string $reportId, Context $context): Response
    {
        try {
            $this->stockValuationService->deleteReport($reportId, $context);
        } catch (StockValuationException $e) {
            return $e->serializeToJsonApiErrors()->toJsonApiErrorResponse(status: Response::HTTP_BAD_REQUEST);
        }

        return new Response('', status: Response::HTTP_NO_CONTENT);
    }
}
