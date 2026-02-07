<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\DatabaseBulkInsertService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The update of `product.sales` is not time critical: it does not need to be calculated immediately. That is why we
 * queue all product sales updates and handle them periodically instead of in-place in each request. This is a
 * performance improvement e.g. when picking products with a lot of orders.
 * See issue: https://github.com/pickware/shopware-plugins/issues/3408
 */
#[AsMessageHandler(handles: ProductSalesUpdateTask::class)]
class ProductSalesUpdater extends ScheduledTaskHandler
{
    private Connection $connection;
    private DatabaseBulkInsertService $bulkInsertWithUpdate;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
        protected readonly ?LoggerInterface $logger,
        Connection $db,
        DatabaseBulkInsertService $bulkInsertWithUpdate,
    ) {
        parent::__construct(
            scheduledTaskRepository: $scheduledTaskRepository,
            exceptionLogger: $logger,
        );

        $this->connection = $db;
        $this->bulkInsertWithUpdate = $bulkInsertWithUpdate;
    }

    public function run(): void
    {
        // We limit this query to 10.000 to not run in any time-out issues. If more than 10.000 products are in the
        // queue, they are handled in the next iteration of the scheduled task (the `created_at` sorting ensures that).
        // This limit number is an educated guess.
        do {
            $productIds = $this->connection->fetchFirstColumn(
                'SELECT LOWER(HEX(`product_id`))
                FROM `pickware_erp_product_sales_update_queue`
                ORDER BY `created_at` ASC
                LIMIT 1000',
            );
            $this->updateSales(array_values($productIds));
        } while (count($productIds) !== 0);
    }

    /**
     * @param String[] $productIds
     */
    public function addProductsToUpdateQueue(array $productIds): void
    {
        $insertValues = array_map(
            fn(string $productId) => [
                'id' => Uuid::randomBytes(),
                'product_id' => hex2bin($productId),
                'product_version_id' => hex2bin(Defaults::LIVE_VERSION),
            ],
            $productIds,
        );

        $this->bulkInsertWithUpdate->insertOnDuplicateKeyUpdate(
            'pickware_erp_product_sales_update_queue',
            $insertValues,
            [],
            ['id'],
        );
    }

    /**
     * @param String[] $productIds
     */
    public function updateSales(array $productIds): void
    {
        if (empty($productIds)) {
            return;
        }

        RetryableTransaction::retryable($this->connection, function() use ($productIds): void {
            // By splitting the SELECT and the UPDATE query we work-around a performance problem. If the
            // queries were executed in one UPDATE ... JOIN query the query time would rise unexpectedly.
            $this->connection->executeStatement(
                'UPDATE product
                INNER JOIN (
                    SELECT
                        `order_line_item`.`product_id` as `product_id`,
                        `order_line_item`.`product_version_id` as `product_version_id`,
                        SUM(`order_line_item`.`quantity`) as `sales`
                    FROM `order_line_item`
                    INNER JOIN `order`
                        ON `order`.`id` = `order_line_item`.`order_id`
                        AND `order`.`version_id` = `order_line_item`.`order_version_id`
                    INNER JOIN `state_machine_state`
                        ON `state_machine_state`.`id` = `order`.state_id
                        AND `state_machine_state`.`technical_name` = :completeStateTechnicalName
                    WHERE
                        `order_line_item`.`product_id` IN (:productIds)
                        AND `order_line_item`.`version_id` = :liveVersionId
                        AND `order_line_item`.`type` = :type
                        AND `order_line_item`.`product_id` IS NOT NULL
                    GROUP BY `order_line_item`.`product_id`
                ) AS productSales
                    ON productSales.`product_id` = product.id
                    AND productSales.`product_version_id` = product.version_id
                SET
                    product.sales = productSales.sales,
                    product.`updated_at` = UTC_TIMESTAMP(3)
                WHERE
                    product.id IN (:productIds)
                    AND product.version_id = :liveVersionId',
                [
                    'productIds' => array_map('hex2bin', $productIds),
                    'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                    'completeStateTechnicalName' => OrderStates::STATE_COMPLETED,
                    'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                ],
                [
                    'productIds' => ArrayParameterType::STRING,
                ],
            );

            $this->connection->executeStatement(
                'DELETE FROM `pickware_erp_product_sales_update_queue` WHERE `product_id` IN (:productIds)',
                ['productIds' => array_map('hex2bin', $productIds)],
                ['productIds' => ArrayParameterType::STRING],
            );
        });
    }
}
