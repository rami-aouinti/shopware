<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockValuation;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use LogicException;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\Sql\SqlUuid;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptStateMachine;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptType;
use Pickware\PickwareErpStarter\StockValuation\Model\PurchaseType;
use Pickware\PickwareErpStarter\StockValuation\Model\ReportDefinition;
use Pickware\PickwareErpStarter\StockValuation\Model\ReportEntity;
use Pickware\PickwareErpStarter\StockValuation\Model\ReportGenerationStep;
use Pickware\PickwareErpStarter\StockValuation\Model\ReportMethod;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\EntitySnapshotService;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;

class StockValuationService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EntitySnapshotService $entitySnapshotService,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Creates a new report in preview state and left for generation.
     *
     * Note to the key "reportDay": Pass it in the format "YYYY-MM-DD", do not try to pass a date with time or a time
     * zone here, as it may lead to unexpected or confusing result. If you do the code will always use the date part of
     * the passed date time string, everything after the date part will be cut off.
     * Example: 2023-12-27T08:02:08+10:00 will be converted to and saved as 2023-12-27 (even tough the date time is
     * represented as 2023-12-26T22:02:08 in UTC).
     */
    public function createReport(array $reportPayload, Context $context): void
    {
        // All newly created reports are previews. Only after explicitly persisting a report this flag can become false.
        $reportPayload['preview'] = true;
        $userId = ContextExtension::findUserId($context);
        if ($userId && !isset($reportPayload['reportingDayTimeZone'])) {
            /** @var UserEntity $user */
            $user = $this->entityManager->getByPrimaryKey(UserDefinition::class, $userId, $context);
            $reportPayload['reportingDayTimeZone'] = $user->getTimeZone();
        }
        $reportPayload['generated'] = false;
        $reportPayload['generationStep'] = ReportGenerationStep::getFirst();
        // The rows are created during the report generation.
        unset($reportPayload['rows']);

        $warehouseId = $reportPayload['warehouseId'];
        $reportPayload['warehouseSnapshot'] = $this->entitySnapshotService->generateSnapshots(
            WarehouseDefinition::class,
            [$warehouseId],
            $context,
        )[$warehouseId];
        unset($reportPayload['warehouse']);

        $this->entityManager->create(ReportDefinition::class, [$reportPayload], $context);
    }

    public function performNextReportGenerationStep(string $reportId, Context $context): void
    {
        /** @var ReportEntity $report */
        $report = $this->entityManager->getByPrimaryKey(ReportDefinition::class, $reportId, $context);

        if ($report->isGenerated()) {
            throw new StockValuationException(new JsonApiErrors([StockValuationError::reportCannotBeRegenerated()]));
        }

        switch ($report->getGenerationStep()) {
            case ReportGenerationStep::ReportCreated:
                $this->updateUntilDateBasedOnGenerationDate($report, $context);
                $this->removeFailedAndPreviewReports($report);
                $this->ensureNoYoungerReportExists($report);
                break;
            case ReportGenerationStep::ReportPrepared:
                $this->calculateStocks($report);
                break;
            case ReportGenerationStep::StocksCalculated:
                $this->calculatePurchases($report);
                break;
            case ReportGenerationStep::PurchasesCalculated:
                $this->calculateAveragePurchasePrice($report, $context);
                break;
            case ReportGenerationStep::AveragePurchasePriceCalculated:
                $this->rateStock($report);
                break;
            case ReportGenerationStep::StockRated:
                $this->saveReport($report, $context);
                break;
            case ReportGenerationStep::ReportSaved:
                throw new StockValuationException(
                    new JsonApiErrors([StockValuationError::reportCannotBeRegenerated()]),
                );
        }

        $this->markCurrentGenerationStepAsFinished($report, $context);
    }

    private function updateUntilDateBasedOnGenerationDate(ReportEntity $report, Context $context): void
    {
        $regularUntilDate = self::calculateRegularUntilDate($report);
        $now = $this->clock->now();
        $untilDate = min($now, $regularUntilDate);
        $report->setUntilDate($untilDate);

        $this->entityManager->update(
            ReportDefinition::class,
            [
                [
                    'id' => $report->getId(),
                    'untilDate' => $untilDate,
                ],
            ],
            $context,
        );
    }

    /**
     * Removes all existing previews and failed reports but the given one.
     */
    private function removeFailedAndPreviewReports(ReportEntity $report): void
    {
        $this->connection->executeStatement('DELETE FROM `pickware_erp_stock_valuation_temp_stock`');
        $this->connection->executeStatement('DELETE FROM `pickware_erp_stock_valuation_temp_purchase`');
        $this->connection->executeStatement(
            'DELETE FROM `pickware_erp_stock_valuation_report`
            WHERE (`preview` = 1 OR `generated` = 0)
                AND `id` != UNHEX(:reportId)',
            ['reportId' => $report->getId()],
        );
    }

    private function ensureNoYoungerReportExists(ReportEntity $report): void
    {
        $untilDateString = DateTime::createFromInterface($report->getUntilDate())
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $youngerReport = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`))
            FROM `pickware_erp_stock_valuation_report` `report`
            WHERE
                `report`.`preview` = 0
                AND `report`.`generated` = 1
                AND `report`.`until_date` >= :untilDate
                AND `report`.`warehouse_id` = UNHEX(:warehouseId)',
            [
                'warehouseId' => $report->getWarehouseId(),
                'untilDate' => $untilDateString,
            ],
        );

        if ($youngerReport !== false) {
            throw new StockValuationException(new JsonApiErrors([StockValuationError::youngerReportExists()]));
        }
    }

    /**
     * Calculates the current stocks of all products in the warehouse and stores them in the temporary stock table.
     */
    private function calculateStocks(ReportEntity $report): void
    {
        $this->connection->executeStatement(
            'INSERT INTO `pickware_erp_stock_valuation_temp_stock`
                (`id`, `report_id`, `product_id`, `product_version_id`, `stock`)
            SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ' AS `id`,
                :reportId AS `report_id`,
                `product`.`id` AS `product_id`,
                `product`.`version_id` AS `product_version_id`,
                -- We want all products to appear in the report, even if they are not in stock. Products with negative
                -- stock (which can appear in combination with Shopify) are rated with a stock of 0.
                GREATEST(COALESCE(`stock`.`quantity`, 0), 0) AS `stock`
            FROM `product`
            INNER JOIN `pickware_erp_pickware_product` pickwareProduct
                ON pickwareProduct.`product_id` = `product`.`id`
                    AND pickwareProduct.`product_version_id` = `product`.`version_id`
            LEFT JOIN (
                -- This subquery calculates the stock of each product in the warehouse by summing up all stock movements
                -- until the report\'s until date. Note that there may be no stock movements for a product and this is
                -- why we use a LEFT JOIN here.
                SELECT
                    `product_id`,
                    `product_version_id`,
                    SUM(`quantity`) AS `quantity`
                FROM (
                    -- Select all outgoing stock movements
                    SELECT
                        `product_id`,
                        `product_version_id`,
                        SUM(`quantity`) * -1 AS `quantity`
                    FROM `pickware_erp_stock_movement`
                    LEFT JOIN `pickware_erp_bin_location` `source_bin_location`
                        ON `pickware_erp_stock_movement`.`source_bin_location_id` = `source_bin_location`.`id`
                    WHERE
                        `pickware_erp_stock_movement`.`created_at` < :untilDate
                        AND (
                            `source_warehouse_id` = :warehouseId
                            OR `source_bin_location`.`warehouse_id` = :warehouseId
                        )
                    GROUP BY `product_id`, `product_version_id`
                    -- Select all incoming stock movements
                    UNION ALL SELECT
                        `product_id`,
                        `product_version_id`,
                        SUM(`quantity`) AS `quantity`
                    FROM `pickware_erp_stock_movement`
                    LEFT JOIN `pickware_erp_bin_location` `destination_bin_location`
                        ON `pickware_erp_stock_movement`.`destination_bin_location_id` = `destination_bin_location`.`id`
                    WHERE
                        `pickware_erp_stock_movement`.`created_at` < :untilDate
                        AND (
                            `destination_warehouse_id` = :warehouseId
                            OR `destination_bin_location`.`warehouse_id` = :warehouseId
                        )
                    GROUP BY `product_id`, `product_version_id`
                ) AS `incoming_and_outgoing_stock`
                GROUP BY `product_id`, `product_version_id`
            ) AS `stock`
                ON `stock`.`product_id` = `product`.`id` AND `product`.`version_id` = `stock`.`product_version_id`
            WHERE
                `product`.`version_id` = :liveVersionId
                AND NOT pickwareProduct.is_stock_management_disabled
                -- Ignore all products that are currently(!) parent products. We do not have historical data for this.
                AND NOT EXISTS (
                    SELECT 1 FROM `product` AS `child`
                    WHERE `child`.`parent_id` = `product`.`id`
                    AND `child`.`parent_version_id` = `product`.`version_id`
                )',
            [
                'reportId' => hex2bin($report->getId()),
                'warehouseId' => hex2bin($report->getWarehouseId()),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'untilDate' => DateTime::createFromInterface($report->getUntilDate())
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        );
    }

    /**
     * Calculates all purchases processed within a report's period. This period is defined as the time span between the
     * last report's and the given report's until date.
     */
    private function calculatePurchases(ReportEntity $report): void
    {
        $this->connection->executeStatement(
            'INSERT INTO `pickware_erp_stock_valuation_temp_purchase` (
                `id`,
                `report_id`,
                `product_id`,
                `product_version_id`,
                `quantity`,
                `purchase_price_net`,
                `date`,
                `type`,
                `average_purchase_price_net`,
                `goods_receipt_line_item_id`,
                `carry_over_report_row_id`
            ) SELECT
                -- This SELECT finds all goods receipt line items that were created within the report period. They
                -- will be interpreted as actual purchases in the report. The report period is defined as the time span
                -- between the last report\'s and the given report\'s until date per product in the same warehouse.
                ' . SqlUuid::UUID_V4_GENERATION . ',
                UNHEX(:reportId) AS `report_id`,
                `goods_receipt_line_item`.`product_id` AS `product_id`,
                `goods_receipt_line_item`.`product_version_id` AS `product_version_id`,
                `goods_receipt_line_item`.`quantity` AS `quantity`,
                `goods_receipt_line_item`.`unit_price` AS `purchase_price_net`,
                `goods_receipt`.`created_at` AS `date`,
                :purchaseTypePurchase AS `type`,
                NULL AS `average_purchase_price_net`,
                `goods_receipt_line_item`.`id` AS `goods_receipt_line_item_id`,
                NULL AS `carry_over_report_row_id`
            FROM `pickware_erp_goods_receipt_line_item` AS `goods_receipt_line_item`
            INNER JOIN `pickware_erp_goods_receipt` `goods_receipt`
                ON `goods_receipt_line_item`.`goods_receipt_id` = `goods_receipt`.`id`
            INNER JOIN `state_machine_state` `goods_receipt_state`
                ON `goods_receipt`.`state_id` = `goods_receipt_state`.`id`
            LEFT JOIN (
                SELECT
                    MAX(`report`.`until_date`) AS `until_date`,
                    `report_row`.`product_id` AS `product_id`,
                    `report_row`.`product_version_id` AS `product_version_id`
                FROM `pickware_erp_stock_valuation_report_row` AS `report_row`
                INNER JOIN `pickware_erp_stock_valuation_report` AS `report` ON `report`.`id` = `report_row`.`report_id`
                WHERE `report`.`preview` = 0
                    AND `report`.`generated` = 1
                    AND `report`.`warehouse_id` = :warehouseId
                GROUP BY `report_row`.`product_id`, `report_row`.`product_version_id`
            ) AS `latest_report`
                ON
                    `latest_report`.`product_id` = `goods_receipt_line_item`.`product_id`
                    AND `latest_report`.`product_version_id` = `goods_receipt_line_item`.`product_version_id`
            WHERE
                `goods_receipt_line_item`.`price` IS NOT NULL
                AND `goods_receipt_line_item`.`product_id` IS NOT NULL
                AND `goods_receipt_line_item`.`quantity` > 0
                AND (
                    `goods_receipt`.`type` = :goodsReceiptTypeSupplier OR `goods_receipt`.`type` = :goodsReceiptTypeFree
                )
                AND `goods_receipt`.`created_at` < :untilDate
                AND `goods_receipt`.`warehouse_id` = :warehouseId
                AND `goods_receipt_state`.`technical_name` = :goodsReceiptStateCompleted
                AND (
                    `goods_receipt`.`created_at` >= `latest_report`.`until_date`
                    OR `latest_report`.`until_date` IS NULL
                )
            UNION ALL SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                UNHEX(:reportId) AS `report_id`,
                -- This select finds the last report for each product an adds its valuation as a "carry-over"
                -- purchase, which works the same as a normal purchase.
                `report_row`.`product_id` AS `product_id`,
                `report_row`.`product_version_id` AS `product_version_id`,
                GREATEST(`report_row`.`stock`, 0) AS `quantity`,
                IF(
                    `report_row`.`stock` <= 0,
                    `report_row`.`average_purchase_price_net`,
                    `report_row`.`valuation_net` / `report_row`.`stock`
                ) AS `purchase_price_net`,
                -- For the date of the carry-over purchase a second is subtracted for usability reasons
                DATE_ADD(`report`.`until_date`, INTERVAL -1 SECOND) AS `date`,
                :purchaseTypeCarryOver AS `type`,
                `report_row`.`average_purchase_price_net` AS `average_purchase_price_net`,
                NULL AS `purchase_stock_ledger_entry_id`,
                `report_row`.`id` AS `carry_over_report_row_id`
            FROM `pickware_erp_stock_valuation_report_row` AS `report_row`
            INNER JOIN `pickware_erp_stock_valuation_report` AS `report` ON `report`.`id` = `report_row`.`report_id`
            INNER JOIN (
                SELECT
                    MAX(`report_row`.`created_at`) as `created_at`,
                    report_row.`product_id`,
                    report_row.`product_version_id`
                FROM `pickware_erp_stock_valuation_report_row` `report_row`
                INNER JOIN `pickware_erp_stock_valuation_report` `report` ON `report`.`id` = `report_row`.`report_id`
                WHERE `report`.`preview` = 0
                    AND `report`.`generated` = 1
                    AND `report`.`warehouse_id` = :warehouseId
                GROUP BY report_row.`product_id`, report_row.`product_version_id`
            ) `newest_product_report_row`
                ON
                    `newest_product_report_row`.`product_id` = `report_row`.`product_id`
                    AND `newest_product_report_row`.`product_version_id` = `report_row`.`product_version_id`
                    AND `newest_product_report_row`.`created_at` = `report_row`.`created_at`
            WHERE `report`.`preview` = 0
                AND `report`.`generated` = 1
                AND `report`.`warehouse_id` = :warehouseId
            ',
            [
                'reportId' => $report->getId(),
                'warehouseId' => hex2bin($report->getWarehouseId()),
                'untilDate' => DateTime::createFromInterface($report->getUntilDate())
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'purchaseTypePurchase' => PurchaseType::Purchase->value,
                'purchaseTypeCarryOver' => PurchaseType::CarryOver->value,
                'goodsReceiptTypeSupplier' => GoodsReceiptType::Supplier->value,
                'goodsReceiptTypeFree' => GoodsReceiptType::Free->value,
                'goodsReceiptStateCompleted' => GoodsReceiptStateMachine::STATE_COMPLETED,
            ],
        );
    }

    /**
     * Calculates the average purchase price for each product in the report.
     *
     * This value is necessary for FiFo, LiFo and Average
     */
    private function calculateAveragePurchasePrice(ReportEntity $report, Context $context): void
    {
        /** @var CurrencyEntity $defaultCurrency */
        $defaultCurrency = $this->entityManager->getByPrimaryKey(
            CurrencyDefinition::class,
            Defaults::CURRENCY,
            $context,
        );

        $this->connection->executeStatement(
            'UPDATE pickware_erp_stock_valuation_temp_stock stock
            LEFT JOIN (
                SELECT
                    ROUND(
                        SUM(`quantity` * `purchase_price_net`) / SUM(`quantity`),
                        :defaultCurrencyDecimals
                    ) AS `average_purchase_price`,
                    `product_id`,
                    `product_version_id`
                FROM `pickware_erp_stock_valuation_temp_purchase`
                WHERE
                    `report_id` = :reportId
                    -- A carry-over purchase may have a quantity of 0. When this is the only purchase for a product,
                    -- a division by 0 would occur. So filter out purchases with a quantity of 0. Normal purchases
                    -- should never have a quantity of 0.
                    AND `quantity` > 0
                GROUP BY `product_id`, `product_version_id`
            ) AS `purchase_average`
                ON
                    `purchase_average`.`product_id` = `stock`.`product_id`
                    AND `purchase_average`.`product_version_id` = `stock`.`product_version_id`
            -- In case there are no purchases for a product, the average purchase price is taken from the last report
            LEFT JOIN (
                SELECT
                    `report_row`.`average_purchase_price_net`,
                    `report_row`.`product_id`,
                    `report_row`.`product_version_id`
                FROM `pickware_erp_stock_valuation_report_row` `report_row`
                INNER JOIN (
                    SELECT
                        MAX(`report_row`.`created_at`) as `created_at`,
                        report_row.`product_id`,
                        report_row.`product_version_id`
                    FROM `pickware_erp_stock_valuation_report_row` `report_row`
                    INNER JOIN `pickware_erp_stock_valuation_report` `report` ON `report`.`id` = `report_row`.`report_id`
                    WHERE `report`.`preview` = 0
                        AND `report`.`generated` = 1
                        AND `report`.`warehouse_id` = :warehouseId
                    GROUP BY report_row.`product_id`, report_row.`product_version_id`
                ) `newest_product_report_row`
                    ON
                        `newest_product_report_row`.`product_id` = `report_row`.`product_id`
                        AND `newest_product_report_row`.`product_version_id` = `report_row`.`product_version_id`
                        AND `newest_product_report_row`.`created_at` = `report_row`.`created_at`
                INNER JOIN `pickware_erp_stock_valuation_report` `report` ON `report`.`id` = `report_row`.`report_id`
                WHERE `report`.`preview` = 0
                    AND `report`.`generated` = 1
                    AND `report`.`warehouse_id` = :warehouseId
            ) AS `latest_report_row`
                ON`latest_report_row`.`product_id` = `stock`.`product_id`
                AND `latest_report_row`.`product_version_id` = `stock`.`product_version_id`
            INNER JOIN `product`
                ON stock.`product_id` = product.`id` AND stock.`product_version_id` = product.`version_id`
            LEFT JOIN `product` AS `parent_product`
                ON
                    `product`.`parent_id` = `parent_product`.`id`
                    AND `product`.`version_id` = `parent_product`.`version_id`
            SET stock.`average_purchase_price_net` = COALESCE(
                purchase_average.average_purchase_price,
                `latest_report_row`.`average_purchase_price_net`,
                JSON_EXTRACT(`product`.`purchase_prices`, CONCAT("$.c", :defaultCurrencyId, ".net")),
                JSON_EXTRACT(`parent_product`.`purchase_prices`, CONCAT("$.c", :defaultCurrencyId, ".net")),
                0.00 -- Fallback when no purchase price is set in the product or its parent
            )
            WHERE `stock`.`report_id` = :reportId',
            [
                'reportId' => hex2bin($report->getId()),
                'defaultCurrencyDecimals' => $defaultCurrency->getTotalRounding()->getDecimals(),
                'defaultCurrencyId' => Defaults::CURRENCY,
                'warehouseId' => hex2bin($report->getWarehouseId()),
            ],
        );
    }

    /**
     * Rates the previously calculated stock according to the report's valuation method.
     */
    private function rateStock(ReportEntity $report): void
    {
        switch ($report->getMethod()) {
            case ReportMethod::FiFo:
            case ReportMethod::LiFo:
                $this->rateStockByFifoOrLifo($report);
                break;
            case ReportMethod::Average:
                $this->rateStockByAverage($report);
                break;
            default:
                throw new LogicException('Unknown report method');
        }
    }

    private function rateStockByAverage(ReportEntity $report): void
    {
        // Rate the stock by the average purchase price
        $this->connection->executeStatement(
            'UPDATE `pickware_erp_stock_valuation_temp_stock` `stock`
            SET `stock`.`valuation_net` = `stock`.`stock` * `stock`.`average_purchase_price_net`
            WHERE `report_id` = :reportId',
            [
                'reportId' => hex2bin($report->getId()),
            ],
        );

        // Mark all purchases as fully used for valuation
        $this->connection->executeStatement(
            'UPDATE `pickware_erp_stock_valuation_temp_purchase` `purchase`
            SET `purchase`.`quantity_used_for_valuation` = `purchase`.`quantity`
            WHERE `report_id` = :reportId',
            [
                'reportId' => hex2bin($report->getId()),
            ],
        );

        // If there is more stock than purchases, the surplus stock is set to the difference of the purchases and the
        // stock
        $this->connection->executeStatement(
            'UPDATE `pickware_erp_stock_valuation_temp_stock` AS `stock`
            LEFT JOIN (
                SELECT
                    SUM(`quantity`) AS `total_quantity`,
                    `product_id`,
                    `product_version_id`
                FROM `pickware_erp_stock_valuation_temp_purchase`
                WHERE `report_id` = :reportId
                GROUP BY `product_id`, `product_version_id`
            ) AS `purchase_sum`
                ON
                    `purchase_sum`.`product_id` = `stock`.`product_id`
                    AND `purchase_sum`.`product_version_id` = `stock`.`product_version_id`
            SET `stock`.`surplus_stock` = `stock`.`stock` - IFNULL(`purchase_sum`.`total_quantity`, 0),
                `stock`.`surplus_purchase_price_net` = `stock`.`average_purchase_price_net`
            WHERE
                IFNULL(`purchase_sum`.`total_quantity`, 0) < `stock`.`stock`
                AND `report_id` = :reportId',
            [
                'reportId' => hex2bin($report->getId()),
            ],
        );
    }

    private function rateStockByFifoOrLifo(ReportEntity $report): void
    {
        $this->connection->executeStatement(
            /** @lang MySQL */
            'DROP PROCEDURE IF EXISTS `pickware_erp_rate_stock`;',
        );

        // Skip the rating of all products that do not have stock. This has a great performance impact for shops with
        // many products that have a stock of 0. Products with a stock of 0 are skipped in the next step.
        $this->connection->executeStatement(
            'UPDATE `pickware_erp_stock_valuation_temp_stock`
            SET `valuation_net` = 0.0
            WHERE
                `stock` = 0
                AND `report_id` = :reportId',
            [
                'reportId' => $report->getId(),
            ],
        );

        $purchaseSorting = ($report->getMethod() === ReportMethod::LiFo) ? 'ASC' : 'DESC';
        $this->connection->executeStatement(
            sprintf(
                /** @lang MySQL */
                'CREATE PROCEDURE `pickware_erp_rate_stock`()
                BEGIN
                    DECLARE `product_id` BINARY(16) DEFAULT NULL;
                    DECLARE `product_version_id` BINARY(16) DEFAULT NULL;
                    DECLARE `stock` INTEGER DEFAULT 0;
                    DECLARE `purchase_quantity` INT;
                    DECLARE `purchase_price_net` DECIMAL(10,2);
                    DECLARE `purchase_id` BINARY(16);

                    DECLARE `valuation_net` DECIMAL(10,2);
                    DECLARE `current_product_id` BINARY(16) DEFAULT NULL;
                    DECLARE `current_stock` INTEGER DEFAULT NULL;

                    DECLARE `finished` INTEGER DEFAULT 0;
                    DECLARE `data_cursor` CURSOR FOR (
                        SELECT
                            `stock`.`product_id`,
                            `stock`.`product_version_id`,
                            `stock`.`stock`,
                            `purchase`.`id`,
                            `purchase`.`quantity`,
                            `purchase`.`purchase_price_net`
                        FROM `pickware_erp_stock_valuation_temp_stock` AS `stock`
                            USE INDEX (`pckwr_erp_stock_valuation_temp_stock.idx.stock_report_product`)
                        LEFT JOIN `pickware_erp_stock_valuation_temp_purchase` `purchase`
                            USE INDEX (`pckwr_erp_stock_vltn_temp_prchs.idx.product_report_date_price`)
                            ON `purchase`.`product_id` = `stock`.`product_id`
                                AND `purchase`.`product_version_id` = `stock`.`product_version_id`
                                AND `purchase`.`report_id` = `stock`.`report_id`
                        WHERE
                            `stock`.`stock` > 0
                            AND `stock`.`report_id` = :reportId
                        ORDER BY
                            `stock`.`product_id`,
                            `purchase`.`date` %s,
                            -- This is an additional sorter to make the stock valuation reports reproducible
                            `purchase`.`purchase_price_net`
                    );
                    DECLARE CONTINUE HANDLER
                        FOR NOT FOUND SET `finished` = 1;

                    OPEN `data_cursor`;

                    -- Loop through all products joined together (left) with their purchases
                    `purchasesLoop`: WHILE `stock` IS NOT NULL DO
                        FETCH `data_cursor` INTO
                            `product_id`,
                            `product_version_id`,
                            `stock`,
                            `purchase_id`,
                            `purchase_quantity`,
                            `purchase_price_net`;

                        IF (NOT(`product_id` <=> `current_product_id`) OR `finished` = 1) THEN
                            -- Next product or no products left
                            IF (`current_product_id` IS NOT NULL AND `current_stock` >= 0) THEN
                                -- We can only determine a valuation for a stock >= 0. If the stock is < 0 the valuation
                                -- will stay null what means that the valuation is not determinable.
                                UPDATE `pickware_erp_stock_valuation_temp_stock` `stock`
                                SET
                                    `stock`.`valuation_net` = `valuation_net`,
                                    -- Save the `current_stock` in case it is not 0. That means not all stock is covered
                                    -- by purchases. The surplus stock will be valued after this loop
                                    `stock`.`surplus_stock` = `current_stock`
                                WHERE `stock`.`product_id` = `current_product_id`;
                            END IF;

                            IF (`finished` = 1) THEN
                                -- No further record found
                                LEAVE `purchasesLoop`;
                            END IF;

                            -- Reset variables for new product
                            SET `current_product_id` = `product_id`;
                            SET `current_stock` = `stock`;
                            SET `valuation_net` = 0.0;
                        END IF;

                        -- Negative stocks cannot be valuated. Just loop to the next product.
                        IF (`stock` < 0) THEN
                            ITERATE `purchasesLoop`;
                        END IF;

                        IF (`purchase_id` IS NULL) THEN
                            -- No purchases for product detail: Set the valuation to 0.0. (If there is stock, the whole
                            -- stock will be counted and valued as surplus stock.)
                            SET `valuation_net` = 0.0;
                            ITERATE `purchasesLoop`;
                        END IF;

                        UPDATE `pickware_erp_stock_valuation_temp_purchase` AS `purchase`
                        SET `purchase`.`quantity_used_for_valuation` = LEAST(`current_stock`, `purchase_quantity`)
                        WHERE `purchase`.`id` = `purchase_id`;

                        SET `valuation_net` = `valuation_net` + LEAST(`current_stock`, `purchase_quantity`) * `purchase_price_net`;
                        SET `current_stock` = GREATEST(`current_stock` - `purchase_quantity`, 0);
                    END WHILE;

                    CLOSE `data_cursor`;

                    -- Adjust the stock valuation for products with surplus stock (means: more stock than the sum
                    -- of purchases)
                    UPDATE `pickware_erp_stock_valuation_temp_stock` AS `stock`
                    LEFT JOIN `pickware_erp_stock_valuation_temp_purchase` AS `lastCarryOver`
                        ON `lastCarryOver`.`product_id` = `stock`.`product_id`
                            AND `lastCarryOver`.`product_version_id` = `stock`.`product_version_id`
                            AND `lastCarryOver`.`type` = :purchaseTypeCarryOver
                            AND `lastCarryOver`.`report_id` = `stock`.`report_id`
                    INNER JOIN `product`
                        ON `product`.id = `stock`.`product_id` AND  `product`.version_id = `stock`.`product_version_id`
                    LEFT JOIN `product` AS `parent_product`
                        ON `product`.`parent_id` = `parent_product`.`id`
                        AND `product`.`version_id` = `parent_product`.`version_id`
                    SET
                        `stock`.`surplus_purchase_price_net` = COALESCE(
                            `lastCarryOver`.`average_purchase_price_net`,
                            JSON_EXTRACT(`product`.`purchase_prices`, CONCAT("$.c", :defaultCurrencyId, ".net")),
                            JSON_EXTRACT(`parent_product`.`purchase_prices`, CONCAT("$.c", :defaultCurrencyId, ".net")),
                            0.0
                        ),
                        `stock`.`valuation_net` = `stock`.`valuation_net` + `stock`.`surplus_stock` * COALESCE(
                            `lastCarryOver`.`average_purchase_price_net`,
                            JSON_EXTRACT(`product`.`purchase_prices`, CONCAT("$.c", :defaultCurrencyId, ".net")),
                            JSON_EXTRACT(`parent_product`.`purchase_prices`, CONCAT("$.c", :defaultCurrencyId, ".net")),
                            0.0
                        )
                    WHERE
                        `stock`.`surplus_stock` != 0
                        AND `stock`.`report_id` = :reportId;
                END;',
                $purchaseSorting,
            ),
            [
                'purchaseTypeCarryOver' => PurchaseType::CarryOver->value,
                'reportId' => hex2bin($report->getId()),
                'defaultCurrencyId' => Defaults::CURRENCY,
            ],
        );

        $this->connection->executeStatement('CALL pickware_erp_rate_stock()');
    }

    /**
     * This persists the report that was calculated in temporary tables into persistent tables.
     */
    private function saveReport(ReportEntity $report, Context $context): void
    {
        /** @var CurrencyEntity $defaultCurrency */
        $defaultCurrency = $this->entityManager->getByPrimaryKey(
            CurrencyDefinition::class,
            Defaults::CURRENCY,
            $context,
        );

        $this->connection->executeStatement(
            'INSERT INTO `pickware_erp_stock_valuation_report_row` (
                `id`,
                `report_id`,
                `product_id`,
                `product_version_id`,
                `product_snapshot`,
                `stock`,
                `valuation_net`,
                `valuation_gross`,
                `tax_rate`,
                `average_purchase_price_net`,
                `surplus_stock`,
                `surplus_purchase_price_net`,
                `created_at`,
                `updated_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ' AS `id`,
                `stock`.`report_id` AS `report_id`,
                `stock`.`product_id` AS `product_id`,
                `stock`.`product_version_id` AS `product_version_id`,
                JSON_OBJECT(
                    "number",
                    `product`.`product_number`,
                    "name",
                    COALESCE(`product_translation`.`name`, `default_product_translation`.`name`, `parent_product_translation`.`name`, `default_parent_product_translation`.`name`),
                    "options",
                    `product_variant_options`.`options`
                ),
                `stock`.`stock` AS `stock`,
                `stock`.`valuation_net` AS `valuation_net`,
                ROUND(
                    `stock`.`valuation_net` * (1 + COALESCE(`tax`.`tax_rate`, `parent_tax`.`tax_rate`, 0.0) / 100),
                    :defaultCurrencyDecimals
                ) AS `valuation_gross`,
                COALESCE(`tax`.`tax_rate`, `parent_tax`.`tax_rate`, 0.0) / 100 AS `tax_rate`,
                `stock`.`average_purchase_price_net` AS `average_purchase_price_net`,
                `stock`.`surplus_stock` AS `surplus_stock`,
                `stock`.`surplus_purchase_price_net` AS `surplus_purchase_price_net`,
                UTC_TIMESTAMP(3) as `created_at`,
                NULL AS `updated_at`
            FROM `pickware_erp_stock_valuation_temp_stock` `stock`
            INNER JOIN `product`
                ON `stock`.`product_id` = `product`.`id`
                AND `stock`.`product_version_id` = `product`.`version_id`
            LEFT JOIN `product_translation`
                ON `product`.`id` = `product_translation`.`product_id`
                AND `product`.`version_id` = `product_translation`.`product_version_id`
                AND `product_translation`.`language_id` = :apiLanguageId
            LEFT JOIN `product_translation` as `default_product_translation`
                ON `product`.`id` = `default_product_translation`.`product_id`
                AND `product`.`version_id` = `default_product_translation`.`product_version_id`
                AND `default_product_translation`.`language_id` = :defaultLanguageId
            LEFT JOIN `product` AS `parent_product`
                ON `product`.`parent_id` = `parent_product`.`id`
                AND `product`.`version_id` = `parent_product`.`version_id`
            LEFT JOIN `product_translation` AS `parent_product_translation`
                ON `parent_product`.`id` = `parent_product_translation`.`product_id`
                AND `parent_product`.`version_id` = `parent_product_translation`.`product_version_id`
                AND `parent_product_translation`.`language_id` = :apiLanguageId
            LEFT JOIN `product_translation` AS `default_parent_product_translation`
                ON `parent_product`.`id` = `default_parent_product_translation`.`product_id`
                AND `parent_product`.`version_id` = `default_parent_product_translation`.`product_version_id`
                AND `default_parent_product_translation`.`language_id` = :defaultLanguageId
            LEFT JOIN (
                SELECT
                    `product_id`,
                    `product_version_id`,
                    GROUP_CONCAT(
                        `property_group_option_translation`.`name`
                        ORDER BY `property_group_option_translation`.`name` ASC
                        SEPARATOR ", "
                    ) AS `options`
                FROM `product_option`
                INNER JOIN `property_group_option`
                    ON `product_option`.`property_group_option_id` = `property_group_option`.`id`
                LEFT JOIN `property_group_option_translation`
                    ON `property_group_option`.`id` = `property_group_option_translation`.`property_group_option_id`
                    AND `property_group_option_translation`.`language_id` = :defaultLanguageId
                WHERE `product_option`.`product_version_id` = :liveVersionId
                GROUP BY `product_id`
            ) AS `product_variant_options`
                ON `product`.`id` = `product_variant_options`.`product_id`
                AND `product`.`version_id` = `product_variant_options`.`product_version_id`
            LEFT JOIN `tax` ON `product`.`tax_id` = `tax`.`id`
            LEFT JOIN `tax` AS parent_tax ON `parent_product`.`tax_id` = `parent_tax`.`id`
            WHERE `stock`.`report_id` = :reportId
            GROUP BY `stock`.`product_id`;',
            [
                'reportId' => hex2bin($report->getId()),
                'apiLanguageId' => hex2bin($context->getLanguageId()),
                'defaultLanguageId' => hex2bin(Defaults::LANGUAGE_SYSTEM),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'defaultCurrencyDecimals' => $defaultCurrency->getTotalRounding()->getDecimals(),
            ],
        );

        $this->connection->executeStatement(
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
            WHERE `reportRow`.`report_id` = :reportId
            AND `reportRow`.`surplus_stock` > 0',
            [
                'purchaseTypeSurplusStock' => PurchaseType::SurplusStock->value,
                'reportId' => hex2bin($report->getId()),
            ],
        );

        $this->connection->executeStatement(
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
                `stock_valuation_report_row`.`id`,
                `purchase`.`date`,
                `purchase`.`purchase_price_net`,
                `purchase`.`quantity`,
                `purchase`.`quantity_used_for_valuation`,
                `purchase`.`type`,
                `purchase`.`goods_receipt_line_item_id`,
                `purchase`.`carry_over_report_row_id`,
                UTC_TIMESTAMP(3),
                NULL
            FROM `pickware_erp_stock_valuation_temp_purchase` AS `purchase`
            INNER JOIN `pickware_erp_stock_valuation_report_row` AS `stock_valuation_report_row`
                ON `stock_valuation_report_row`.`product_id` = `purchase`.`product_id`
                AND `stock_valuation_report_row`.`product_version_id` = `purchase`.`product_version_id`
                AND `stock_valuation_report_row`.`report_id` = :reportId
            WHERE `purchase`.`report_id` = :reportId',
            [
                'reportId' => hex2bin($report->getId()),
            ],
        );

        $this->connection->executeStatement('DELETE FROM `pickware_erp_stock_valuation_temp_stock`');
        $this->connection->executeStatement('DELETE FROM `pickware_erp_stock_valuation_temp_purchase`');
    }

    private function markCurrentGenerationStepAsFinished(ReportEntity $report, Context $context): void
    {
        $nextGenerationStep = $report->getGenerationStep()->getNext();
        $payload = [
            'id' => $report->getId(),
            'generationStep' => $nextGenerationStep,
        ];
        if ($nextGenerationStep?->isLast()) {
            $payload['generated'] = true;
        }

        $this->entityManager->update(ReportDefinition::class, [$payload], $context);
    }

    /**
     * Persists the report by removing the preview flag.
     */
    public function persistReport(string $reportId, Context $context): void
    {
        /** @var ReportEntity $report */
        $report = $this->entityManager->getByPrimaryKey(ReportDefinition::class, $reportId, $context);

        $errors = new JsonApiErrors();
        if (!$report->isGenerated()) {
            $errors->addError(StockValuationError::reportNotCompletelyGenerated());
        }
        if ($report->getUntilDate() != self::calculateRegularUntilDate($report)) {
            $errors->addError(StockValuationError::reportDoesNotFullyIncludeReportingDay());
        }
        if (count($errors) !== 0) {
            throw new StockValuationException($errors);
        }

        $this->entityManager->update(
            ReportDefinition::class,
            [
                [
                    'id' => $reportId,
                    'preview' => false,
                ],
            ],
            $context,
        );
    }

    public function getDeletableReportIdsInWarehouses(array $warehouseIds): array
    {
        $warehouseCondition = count($warehouseIds) > 0 ? 'AND `warehouse_id` IN (:warehouseIds)' : '';

        $deletableIds = $this->connection->fetchFirstColumn(
            'SELECT `report`.`id`
            FROM `pickware_erp_stock_valuation_report` `report`
            INNER JOIN (
                SELECT
                    `warehouse_id`,
                    MAX(`reporting_day`) AS `maxReportingDay`
                FROM `pickware_erp_stock_valuation_report`
                WHERE
                    `preview` = 0
                    ' . $warehouseCondition . '
                GROUP BY `warehouse_id`
            ) `youngest_reporting_day_per_warehouse` ON
                `report`.`warehouse_id` = `youngest_reporting_day_per_warehouse`.`warehouse_id`
                AND `report`.`reporting_day` = `youngest_reporting_day_per_warehouse`.`maxReportingDay`',
            ['warehouseIds' => array_map('hex2bin', $warehouseIds)],
            ['warehouseIds' => ArrayParameterType::STRING],
        );

        return array_map('bin2hex', $deletableIds);
    }

    public function deleteReport(string $reportId, Context $context): void
    {
        /** @var ReportEntity $report */
        $report = $this->entityManager->getByPrimaryKey(ReportDefinition::class, $reportId, $context);

        if (
            $report->getWarehouseId()
            && !in_array($reportId, $this->getDeletableReportIdsInWarehouses([$report->getWarehouseId()]))
        ) {
            // The given report is not the newest report in its warehouse
            throw new StockValuationException(new JsonApiErrors([
                StockValuationError::olderReportInWarehouseCannotBeDeleted(),
            ]));
        }

        // For performance reasons, we do not use the entity manager to delete the report and its rows
        // since a lot of events would be triggered and have lead to the process crashing in the past.
        $this->connection->executeStatement(
            'DELETE FROM `pickware_erp_stock_valuation_report`
            WHERE `id` = :reportId',
            ['reportId' => hex2bin($reportId)],
        );
    }

    private static function calculateRegularUntilDate(ReportEntity $report): DateTimeImmutable
    {
        $beginOfReportingDayInTimeZone = new DateTime(
            $report->getReportingDay()->format(Defaults::STORAGE_DATE_FORMAT),
            new DateTimeZone($report->getReportingDayTimeZone()),
        );

        $untilDate = $beginOfReportingDayInTimeZone
            ->add(new DateInterval('P1D'))
            ->setTimezone(new DateTimeZone('UTC'));

        return DateTimeImmutable::createFromMutable($untilDate);
    }
}
