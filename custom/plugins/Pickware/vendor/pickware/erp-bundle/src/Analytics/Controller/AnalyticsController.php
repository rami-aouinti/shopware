<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Analytics\Controller;

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\ResponseFactory;
use Pickware\PickwareErpStarter\Analytics\AnalyticsAggregationSessionService;
use Pickware\PickwareErpStarter\Analytics\AnalyticsException;
use Pickware\PickwareErpStarter\Analytics\AnalyticsReportConfigService;
use Pickware\PickwareErpStarter\Analytics\AnalyticsService;
use Pickware\PickwareErpStarter\Analytics\Model\AnalyticsAggregationSessionDefinition;
use Pickware\PickwareErpStarter\Analytics\Model\AnalyticsReportConfigDefinition;
use Shopware\Core\Framework\Api\Response\ResponseFactoryRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class AnalyticsController
{
    private EntityManager $entityManager;
    private AnalyticsService $analyticsService;
    private AnalyticsAggregationSessionService $analyticsAggregationSessionService;
    private AnalyticsReportConfigService $analyticsReportConfigService;
    private ResponseFactoryRegistry $responseFactoryRegistry;

    public function __construct(
        EntityManager $entityManager,
        AnalyticsService $analyticsService,
        AnalyticsAggregationSessionService $analyticsAggregationSessionService,
        AnalyticsReportConfigService $analyticsReportConfigService,
        ResponseFactoryRegistry $responseFactoryRegistry,
    ) {
        $this->entityManager = $entityManager;
        $this->analyticsService = $analyticsService;
        $this->analyticsAggregationSessionService = $analyticsAggregationSessionService;
        $this->analyticsReportConfigService = $analyticsReportConfigService;
        $this->responseFactoryRegistry = $responseFactoryRegistry;
    }

    #[Route(path: '/api/_action/pickware-erp/analytics/ensure-aggregation-session-exists', methods: ['POST'])]
    public function ensureAggregationSessionExists(Request $request, Context $context): Response
    {
        $userId = $request->get('userId');
        if (!$userId || !Uuid::isValid($userId)) {
            return ResponseFactory::createUuidParameterMissingResponse('userId');
        }

        $aggregationTechnicalName = $request->get('aggregationTechnicalName');
        if (!$aggregationTechnicalName) {
            return ResponseFactory::createParameterMissingResponse('aggregationTechnicalName');
        }

        try {
            $aggregationSessionId = $this->analyticsAggregationSessionService->ensureAggregationSessionExists(
                $aggregationTechnicalName,
                $userId,
                $context,
            );
        } catch (AnalyticsException $e) {
            return $e->serializeToJsonApiError()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        $session = $this->entityManager->findByPrimaryKey(
            AnalyticsAggregationSessionDefinition::class,
            $aggregationSessionId,
            $context,
        );
        $responseFactory = $this->responseFactoryRegistry->getType($request);

        return $responseFactory->createDetailResponse(
            new Criteria(),
            $session,
            $this->entityManager->getEntityDefinition(AnalyticsAggregationSessionDefinition::class),
            $request,
            $context,
        );
    }

    #[Route(path: '/api/_action/pickware-erp/analytics/ensure-report-config-exists', methods: ['POST'])]
    public function ensureReportConfigExists(Request $request, Context $context): Response
    {
        $aggregationSessionId = $request->get('aggregationSessionId');
        if (!$aggregationSessionId || !Uuid::isValid($aggregationSessionId)) {
            return ResponseFactory::createUuidParameterMissingResponse('aggregationSessionId');
        }

        $reportTechnicalName = $request->get('reportTechnicalName');
        if (!$reportTechnicalName) {
            return ResponseFactory::createParameterMissingResponse('reportTechnicalName');
        }

        try {
            $reportConfigId = $this->analyticsReportConfigService->ensureReportConfigExists(
                $reportTechnicalName,
                $aggregationSessionId,
                $context,
            );
        } catch (AnalyticsException $e) {
            return $e->serializeToJsonApiError()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        $reportConfig = $this->entityManager->findByPrimaryKey(
            AnalyticsReportConfigDefinition::class,
            $reportConfigId,
            $context,
        );
        $responseFactory = $this->responseFactoryRegistry->getType($request);

        return $responseFactory->createDetailResponse(
            new Criteria(),
            $reportConfig,
            $this->entityManager->getEntityDefinition(AnalyticsReportConfigDefinition::class),
            $request,
            $context,
        );
    }

    #[Route(path: '/api/_action/pickware-erp/analytics/aggregate', methods: ['POST'])]
    public function aggregate(Request $request, Context $context): Response
    {
        $aggregationSessionId = $request->get('aggregationSessionId');
        if (!$aggregationSessionId || !Uuid::isValid($aggregationSessionId)) {
            return ResponseFactory::createUuidParameterMissingResponse('aggregationSessionId');
        }

        $aggregationTechnicalName = $request->get('aggregationTechnicalName');
        if (!$aggregationTechnicalName) {
            return ResponseFactory::createParameterMissingResponse('aggregationTechnicalName');
        }

        try {
            $this->analyticsService->aggregate(
                $aggregationTechnicalName,
                $aggregationSessionId,
                $context,
            );
        } catch (AnalyticsException $e) {
            return $e->serializeToJsonApiError()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse();
    }

    #[Route(path: '/api/_action/pickware-erp/analytics/calculate-report-list-items', methods: ['POST'])]
    public function calculateReportListItems(Request $request, Context $context): Response
    {
        $reportConfigId = $request->get('reportConfigId');
        if (!$reportConfigId || !Uuid::isValid($reportConfigId)) {
            return ResponseFactory::createUuidParameterMissingResponse('reportConfigId');
        }

        $reportTechnicalName = $request->get('reportTechnicalName');
        if (!$reportTechnicalName) {
            return ResponseFactory::createParameterMissingResponse('reportTechnicalName');
        }

        try {
            $this->analyticsService->calculateReportListItems(
                $reportTechnicalName,
                $reportConfigId,
                $context,
            );
        } catch (AnalyticsException $e) {
            return $e->serializeToJsonApiError()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse();
    }
}
