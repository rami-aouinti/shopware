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

use DateTime;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Analytics\DependencyInjection\AnalyticsAggregatorRegistry;
use Pickware\PickwareErpStarter\Analytics\DependencyInjection\AnalyticsReportListItemCalculatorRegistry;
use Pickware\PickwareErpStarter\Analytics\DependencyInjection\AnalyticsReportListItemDefinitionRegistry;
use Pickware\PickwareErpStarter\Analytics\Model\AnalyticsAggregationSessionDefinition;
use Pickware\PickwareErpStarter\Analytics\Model\AnalyticsAggregationSessionEntity;
use Shopware\Core\Framework\Context;

class AnalyticsService
{
    private EntityManager $entityManager;
    private Connection $connection;
    private AnalyticsAggregatorRegistry $aggregatorRegistry;
    private AnalyticsReportListItemCalculatorRegistry $reportListItemCalculatorRegistry;
    private AnalyticsReportListItemDefinitionRegistry $reportListItemDefinitionRegistry;

    public function __construct(
        EntityManager $entityManager,
        Connection $connection,
        AnalyticsAggregatorRegistry $aggregatorRegistry,
        AnalyticsReportListItemCalculatorRegistry $reportListItemCalculatorRegistry,
        AnalyticsReportListItemDefinitionRegistry $reportListItemDefinitionRegistry,
    ) {
        $this->entityManager = $entityManager;
        $this->connection = $connection;
        $this->aggregatorRegistry = $aggregatorRegistry;
        $this->reportListItemCalculatorRegistry = $reportListItemCalculatorRegistry;
        $this->reportListItemDefinitionRegistry = $reportListItemDefinitionRegistry;
    }

    public function aggregate(string $aggregationTechnicalName, string $aggregationSessionId, Context $context): void
    {
        /** @var AnalyticsAggregationSessionEntity $aggregationSession */
        $aggregationSession = $this->entityManager->getByPrimaryKey(
            AnalyticsAggregationSessionDefinition::class,
            $aggregationSessionId,
            $context,
            ['reportConfigs'],
        );

        $this->clearAggregationItems($aggregationTechnicalName, $aggregationSessionId);

        try {
            $this->aggregatorRegistry
                ->getAnalyticsAggregatorByAggregationTechnicalName($aggregationTechnicalName)
                ->aggregate($aggregationSessionId, $context);
        } catch (AnalyticsException $exception) {
            throw $exception->addAggregationSessionIdToErrorMeta($aggregationSessionId);
        }

        $this->entityManager->update(
            AnalyticsAggregationSessionDefinition::class,
            [
                [
                    'id' => $aggregationSessionId,
                    'lastCalculation' => new DateTime(),
                ],
            ],
            $context,
        );

        // After the aggregation was reaggregated, reset all reports of this aggregation (i.e. recalculate them)
        foreach ($aggregationSession->getReportConfigs() as $reportConfig) {
            $this->calculateReportListItems(
                $reportConfig->getReportTechnicalName(),
                $reportConfig->getId(),
                $context,
            );
        }
    }

    public function calculateReportListItems(string $reportTechnicalName, string $reportConfigId, Context $context): void
    {
        $this->clearReportListItems($reportTechnicalName, $reportConfigId);

        $this->reportListItemCalculatorRegistry
            ->getAnalyticsReportListItemCalculatorByReportTechnicalName($reportTechnicalName)
            ->calculate($reportConfigId, $context);
    }

    private function clearReportListItems(string $reportTechnicalName, string $reportConfigId): void
    {
        $this->connection->executeStatement(
            sprintf(
                'DELETE FROM `%1$s` WHERE `%1$s`.`report_config_id` = :reportConfigId',
                $this->reportListItemDefinitionRegistry
                    ->getAnalyticsReportListItemDefinitionByReportTechnicalName($reportTechnicalName)
                    ->getEntityName(),
            ),
            ['reportConfigId' => hex2bin($reportConfigId)],
        );
    }

    private function clearAggregationItems(string $aggregationTechnicalName, string $aggregationSessionId): void
    {
        $this->connection->executeStatement(
            sprintf(
                'DELETE FROM `%1$s` WHERE `%1$s`.`aggregation_session_id` = :aggregationSessionId',
                $this->aggregatorRegistry
                    ->getAnalyticsAggregatorByAggregationTechnicalName($aggregationTechnicalName)
                    ->getAggregationItemsTableName(),
            ),
            ['aggregationSessionId' => hex2bin($aggregationSessionId)],
        );
    }
}
