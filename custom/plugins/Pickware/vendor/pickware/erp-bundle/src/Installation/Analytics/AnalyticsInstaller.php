<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Installation\Analytics;

use Doctrine\DBAL\Connection;

class AnalyticsInstaller
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * @param AnalyticsReport[] $analyticsProfiles
     */
    public function installReports(array $analyticsProfiles): void
    {
        foreach ($analyticsProfiles as $analyticsProfile) {
            $this->db->executeStatement(
                'INSERT INTO `pickware_erp_analytics_report` (
                    `technical_name`,
                    `aggregation_technical_name`,
                    `created_at`
                ) VALUES (
                    :technicalName,
                    :aggregationTechnicalName,
                    UTC_TIMESTAMP(3)
                ) ON DUPLICATE KEY UPDATE `technical_name` = `technical_name`',
                [
                    'technicalName' => $analyticsProfile->getTechnicalName(),
                    'aggregationTechnicalName' => $analyticsProfile->getAggregationTechnicalName(),
                ],
            );
        }
    }

    /**
     * @param AnalyticsAggregation[] $analyticsAggregations
     */
    public function installAggregations(array $analyticsAggregations): void
    {
        foreach ($analyticsAggregations as $analyticsAggregation) {
            $this->db->executeStatement(
                'INSERT INTO `pickware_erp_analytics_aggregation` (
                    `technical_name`,
                    `created_at`
                ) VALUES (
                    :technicalName,
                    UTC_TIMESTAMP(3)
                ) ON DUPLICATE KEY UPDATE `technical_name` = `technical_name`',
                ['technicalName' => $analyticsAggregation->getTechnicalName()],
            );
        }
    }
}
