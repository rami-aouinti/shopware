<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Analytics;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Analytics\DependencyInjection\AnalyticsReportConfigFactoryRegistry;
use Pickware\PickwareErpStarter\Analytics\Model\AnalyticsReportConfigDefinition;
use Pickware\PickwareErpStarter\Analytics\Model\AnalyticsReportConfigEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class AnalyticsReportConfigService
{
    private EntityManager $entityManager;
    private AnalyticsService $analyticsService;
    private AnalyticsReportConfigFactoryRegistry $reportConfigFactoryRegistry;

    public function __construct(
        EntityManager $entityManager,
        AnalyticsService $analyticsService,
        AnalyticsReportConfigFactoryRegistry $reportConfigFactoryRegistry,
    ) {
        $this->entityManager = $entityManager;
        $this->analyticsService = $analyticsService;
        $this->reportConfigFactoryRegistry = $reportConfigFactoryRegistry;
    }

    /**
     * Ensures that a report config for the given report technical name and user exists. Recalculates the report's list
     * items for the ensured config.
     */
    public function ensureReportConfigExists(string $reportTechnicalName, string $aggregationSessionId, Context $context): string
    {
        /** @var AnalyticsReportConfigEntity|null $reportConfig */
        $reportConfig = $this->entityManager->findOneBy(
            AnalyticsReportConfigDefinition::class,
            [
                'report.technicalName' => $reportTechnicalName,
                'aggregationSessionId' => $aggregationSessionId,
            ],
            $context,
        );

        if (!$reportConfig) {
            $reportConfigId = Uuid::randomHex();
            $this->entityManager->create(
                AnalyticsReportConfigDefinition::class,
                [
                    [
                        'id' => $reportConfigId,
                        'reportTechnicalName' => $reportTechnicalName,
                        'aggregationSessionId' => $aggregationSessionId,
                        'listQuery' => null,
                        'calculatorConfig' => $this->reportConfigFactoryRegistry
                            ->getAnalyticsReportConfigFactoryByReportTechnicalName($reportTechnicalName)
                            ->createDefaultCalculatorConfig()
                            ->jsonSerialize(),
                    ],
                ],
                $context,
            );

            $reportConfig = $this->entityManager->getByPrimaryKey(
                AnalyticsReportConfigDefinition::class,
                $reportConfigId,
                $context,
            );
        }

        $this->analyticsService->calculateReportListItems($reportTechnicalName, $reportConfig->getId(), $context);

        return $reportConfig->getId();
    }
}
