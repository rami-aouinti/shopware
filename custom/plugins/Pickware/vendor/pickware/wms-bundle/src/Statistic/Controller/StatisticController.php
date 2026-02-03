<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Statistic\Controller;

use Pickware\PickwareWms\Statistic\Dto\PickingStatisticFilter;
use Pickware\PickwareWms\Statistic\Service\OutputAnalysisCalculator;
use Pickware\PickwareWms\Statistic\Service\PickingStatisticDateTimeOfEarliestLogEntryCalculator;
use Pickware\PickwareWms\Statistic\Service\PickingStatisticOverviewCalculator;
use Pickware\PickwareWms\Statistic\Service\PickingStatisticPerformanceAnalysisDeliveriesCalculator;
use Pickware\PickwareWms\Statistic\Service\PickingStatisticPerformanceAnalysisPickedUnitsCalculator;
use Pickware\PickwareWms\Statistic\Service\PickingStatisticPerformanceAnalysisPicksCalculator;
use Pickware\PickwareWms\Statistic\Service\PickingStatisticPerformanceAnalysisShippedDeliveriesCalculator;
use Pickware\PickwareWms\Statistic\Service\PickingStatisticTabularPerformanceAnalysisCalculator;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class StatisticController
{
    public function __construct(
        private readonly PickingStatisticOverviewCalculator $pickingStatisticOverviewCalculator,
        private readonly PickingStatisticPerformanceAnalysisPicksCalculator $pickingStatisticPerformanceAnalysisPicksCalculator,
        private readonly PickingStatisticPerformanceAnalysisDeliveriesCalculator $pickingStatisticPerformanceAnalysisDeliveriesCalculator,
        private readonly PickingStatisticPerformanceAnalysisShippedDeliveriesCalculator $pickingStatisticPerformanceAnalysisShippedDeliveriesCalculator,
        private readonly PickingStatisticPerformanceAnalysisPickedUnitsCalculator $pickingStatisticPerformanceAnalysisPickedUnitsCalculator,
        private readonly PickingStatisticDateTimeOfEarliestLogEntryCalculator $pickingStatisticDateTimeOfEarliestLogEntryCalculator,
        private readonly OutputAnalysisCalculator $outputAnalysisCalculator,
        private readonly PickingStatisticTabularPerformanceAnalysisCalculator $pickingStatisticTabularPerformanceAnalysisCalculator,
    ) {}

    /**
     * The date values must be in UTC.
     */
    #[Route(path: '/api/_action/pickware-wms/statistic/create-overview', methods: ['POST'])]
    public function createStatisticOverview(
        #[JsonParameter] PickingStatisticFilter $statisticFilter,
    ): Response {
        return new JsonResponse($this->pickingStatisticOverviewCalculator->calculatePickStatisticOverview($statisticFilter));
    }

    /**
     * The date values must be in UTC.
     */
    #[Route(path: '/api/_action/pickware-wms/statistic/create-picks-performance-analysis', methods: ['POST'])]
    public function createStatisticPicksPerformanceAnalysis(
        #[JsonParameter] PickingStatisticFilter $statisticFilter,
        Context $context,
    ): Response {
        return new JsonResponse($this->pickingStatisticPerformanceAnalysisPicksCalculator->calculateStatisticPerformanceAnalysis($statisticFilter, $context));
    }

    /**
     * The date values must be in UTC.
     */
    #[Route(path: '/api/_action/pickware-wms/statistic/create-deliveries-performance-analysis', methods: ['POST'])]
    public function createStatisticDeliveriesPerformanceAnalysis(
        #[JsonParameter] PickingStatisticFilter $statisticFilter,
        Context $context,
    ): Response {
        return new JsonResponse($this->pickingStatisticPerformanceAnalysisDeliveriesCalculator->calculateStatisticPerformanceAnalysis($statisticFilter, $context));
    }

    /**
     * The date values must be in UTC.
     */
    #[Route(path: '/api/_action/pickware-wms/statistic/create-shipped-deliveries-performance-analysis', methods: ['POST'])]
    public function createStatisticShippedDeliveriesPerformanceAnalysis(
        #[JsonParameter] PickingStatisticFilter $statisticFilter,
        Context $context,
    ): Response {
        return new JsonResponse($this->pickingStatisticPerformanceAnalysisShippedDeliveriesCalculator->calculateStatisticPerformanceAnalysis($statisticFilter, $context));
    }

    /**
     * The date values must be in UTC.
     */
    #[Route(path: '/api/_action/pickware-wms/statistic/create-picked-units-performance-analysis', methods: ['POST'])]
    public function createStatisticPickedUnitsPerformanceAnalysis(
        #[JsonParameter] PickingStatisticFilter $statisticFilter,
        Context $context,
    ): Response {
        return new JsonResponse($this->pickingStatisticPerformanceAnalysisPickedUnitsCalculator->calculateStatisticPerformanceAnalysis($statisticFilter, $context));
    }

    /**
     * The date values must be in UTC.
     */
    #[Route(path: '/api/_action/pickware-wms/statistic/create-output-analysis', methods: ['POST'])]
    public function createOutputAnalysis(
        #[JsonParameter] PickingStatisticFilter $statisticFilter,
    ): Response {
        return new JsonResponse($this->outputAnalysisCalculator->calculateOutputAnalysis($statisticFilter));
    }

    /**
     * The date values must be in UTC.
     */
    #[Route(path: '/api/_action/pickware-wms/statistic/create-tabular-performance-analysis', methods: ['POST'])]
    public function createTabularPerformanceAnalysis(
        #[JsonParameter] PickingStatisticFilter $statisticFilter,
        Context $context,
    ): Response {
        return new JsonResponse($this->pickingStatisticTabularPerformanceAnalysisCalculator->calculateTabularPerformanceAnalysis($statisticFilter, $context));
    }

    #[Route(path: '/api/_action/pickware-wms/statistic/datetime-of-earliest-log-entry', methods: ['GET'])]
    public function calculateStartDate(): JsonResponse
    {
        return new JsonResponse([
            'dateTimeOfEarliestLogEntry' => $this->pickingStatisticDateTimeOfEarliestLogEntryCalculator->calculateDateTimeOfEarliestLogEntry()?->format('Y-m-d\\TH:i:s.v\\Z'),
        ]);
    }
}
