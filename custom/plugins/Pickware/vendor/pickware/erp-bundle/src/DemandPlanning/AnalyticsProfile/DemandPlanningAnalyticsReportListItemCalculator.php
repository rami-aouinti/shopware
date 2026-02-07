<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemandPlanning\AnalyticsProfile;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\PickwareErpStarter\Analytics\AnalyticsReportListItemCalculator;
use Shopware\Core\Framework\Context;

class DemandPlanningAnalyticsReportListItemCalculator implements AnalyticsReportListItemCalculator
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function calculate(string $reportConfigId, Context $context): void
    {
        $this->connection->executeStatement(
            'INSERT INTO `pickware_erp_demand_planning_list_item` (
                `id`,
                `report_config_id`,
                `product_id`,
                `product_version_id`,
                `sales`,
                `sales_prediction`,
                `reserved_stock`,
                `available_stock`,
                `stock`,
                `reorder_point`,
                `incoming_stock`,
                `purchase_suggestion`,
                `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                :reportConfigId,
                `demand_planning_item`.`product_id`,
                `demand_planning_item`.`product_version_id`,
                `demand_planning_item`.`sales`,
                `demand_planning_item`.`sales_prediction`,
                `demand_planning_item`.`reserved_stock`,
                `demand_planning_item`.`available_stock`,
                `demand_planning_item`.`stock`,
                `demand_planning_item`.`reorder_point`,
                `demand_planning_item`.`incoming_stock`,
                `demand_planning_item`.`purchase_suggestion`,
                UTC_TIMESTAMP(3)
            FROM `pickware_erp_analytics_aggregation_item_demand_planning` `demand_planning_item`
            JOIN `pickware_erp_analytics_report_config` `report_config`
                ON `report_config`.`aggregation_session_id` = `demand_planning_item`.`aggregation_session_id`
            WHERE `report_config`.`id` = :reportConfigId',
            ['reportConfigId' => hex2bin($reportConfigId)],
        );
    }

    public function getReportTechnicalName(): string
    {
        return DemandPlanningAnalyticsReport::TECHNICAL_NAME;
    }
}
