<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Migration;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\PickwareErpStarter\StockValuation\Model\PurchaseType;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1765548243AddSurplusStockPurchaseToExistingStockValuations extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1765548243;
    }

    public function update(Connection $connection): void
    {
        // This is the same query that is used in
        // Pickware\PickwareErpStarter\StockValuation\StockValuationService::finalizeStockValuationReport,
        // but it is not filtered for a single report
        $connection->executeStatement(
            'INSERT INTO `pickware_erp_stock_valuation_report_purchase` (
                `id`,
                `report_row_id`,
                `date`,
                `purchase_price_net`,
                `quantity`,
                `quantity_used_for_valuation`,
                `type`,
                `goods_receipt_line_item_id`,
                `carry_over_report_row_id`,
                `created_at`,
                `updated_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                `reportRow`.`id`,
                DATE_SUB(`report`.`until_date`, INTERVAL 1 SECOND),
                `reportRow`.`surplus_purchase_price_net`,
                `reportRow`.`surplus_stock`,
                `reportRow`.`surplus_stock`,
                :purchaseTypeSurplusStock,
                NULL,
                NULL,
                UTC_TIMESTAMP(3),
                NULL
            FROM `pickware_erp_stock_valuation_report_row` AS `reportRow`
            INNER JOIN `pickware_erp_stock_valuation_report` AS `report`
                ON `report`.`id` = `reportRow`.`report_id`
            WHERE `reportRow`.`surplus_stock` > 0',
            [
                'purchaseTypeSurplusStock' => PurchaseType::SurplusStock->value,
            ],
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
