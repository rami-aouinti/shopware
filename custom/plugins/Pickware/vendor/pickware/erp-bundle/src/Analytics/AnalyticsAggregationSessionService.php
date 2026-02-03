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

use DateInterval;
use DateTime;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Analytics\DependencyInjection\AnalyticsAggregatorConfigFactoryRegistry;
use Pickware\PickwareErpStarter\Analytics\Model\AnalyticsAggregationSessionDefinition;
use Pickware\PickwareErpStarter\Analytics\Model\AnalyticsAggregationSessionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class AnalyticsAggregationSessionService
{
    private EntityManager $entityManager;
    private AnalyticsService $analyticsService;
    private AnalyticsAggregatorConfigFactoryRegistry $aggregatorConfigFactoryRegistry;

    public function __construct(
        EntityManager $entityManager,
        AnalyticsService $analyticsService,
        AnalyticsAggregatorConfigFactoryRegistry $aggregatorConfigFactoryRegistry,
    ) {
        $this->entityManager = $entityManager;
        $this->analyticsService = $analyticsService;
        $this->aggregatorConfigFactoryRegistry = $aggregatorConfigFactoryRegistry;
    }

    /**
     * Ensures that a session for the given aggregation and user exists. Reaggregates the session's list items when the
     * last calculation is outdated or the session is about to be created.
     */
    public function ensureAggregationSessionExists(string $aggregationTechnicalName, string $userId, Context $context): string
    {
        /** @var AnalyticsAggregationSessionEntity|null $session */
        $session = $this->entityManager->findOneBy(
            AnalyticsAggregationSessionDefinition::class,
            [
                'aggregation.technicalName' => $aggregationTechnicalName,
                'userId' => $userId,
            ],
            $context,
        );

        if (!$session) {
            $sessionId = Uuid::randomHex();
            $this->entityManager->create(
                AnalyticsAggregationSessionDefinition::class,
                [
                    [
                        'id' => $sessionId,
                        'aggregationTechnicalName' => $aggregationTechnicalName,
                        'userId' => $userId,
                        'config' => $this->aggregatorConfigFactoryRegistry
                            ->getAnalyticsAggregatorConfigFactoryByAggregationTechnicalName($aggregationTechnicalName)
                            ->createDefaultAggregatorConfig()
                            ->jsonSerialize(),
                        'lastCalculation' => null,
                    ],
                ],
                $context,
            );

            $session = $this->entityManager->getByPrimaryKey(
                AnalyticsAggregationSessionDefinition::class,
                $sessionId,
                $context,
            );
        }

        if (!$session->getLastCalculation() || ($session->getLastCalculation() < $this->getLastCalculationThreshold())) {
            $this->analyticsService->aggregate($aggregationTechnicalName, $session->getId(), $context);
        }

        return $session->getId();
    }

    private function getLastCalculationThreshold(): DateTime
    {
        $threshold = new DateTime();
        $threshold->sub(new DateInterval('P1D'));

        return $threshold;
    }
}
