<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking\ProductSummary;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityPreWriteValidationEventDispatcher;
use Pickware\PickwareErpStarter\Stock\WarehouseStockUpdatedEvent;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeCountingProcessDefinition;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeCountingProcessItemDefinition;
use Pickware\PickwareErpStarter\Stocktaking\Model\StocktakeDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StocktakeProductSummaryUpdater implements EventSubscriberInterface
{
    private Connection $connection;
    private StocktakeProductSummaryCalculator $summaryCalculator;

    public function __construct(Connection $db, StocktakeProductSummaryCalculator $summaryCalculator)
    {
        $this->connection = $db;
        $this->summaryCalculator = $summaryCalculator;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Triggered from pickware erp when the warehouse stock of certain products in certain warehouses is updated
            WarehouseStockUpdatedEvent::EVENT_NAME => 'warehouseStockUpdated',
            // Triggered when a counting process item is created or updated
            StocktakeCountingProcessItemDefinition::ENTITY_WRITTEN_EVENT => 'stocktakeCountingProcessItemWritten',
            // Triggered when a counting process item is deleted
            StocktakeCountingProcessItemDefinition::ENTITY_DELETED_EVENT => 'stocktakeCountingProcessItemDeleted',
            // Triggered when a counting process is created or updated (i.e. moved to another stocktake)
            StocktakeCountingProcessDefinition::ENTITY_WRITTEN_EVENT => 'stocktakeCountingProcessWritten',
            // Triggered when a counting process is deleted. The countingProcess.deleted event is also triggered, but
            // as we need additional information to recalculate the correct summaries we need to listen to this event.
            EntityWrittenContainerEvent::class => 'entityWritten',
            // Triggered when a counting process is created or updated
            StocktakeDefinition::ENTITY_WRITTEN_EVENT => 'stocktakeWritten',
            EntityPreWriteValidationEventDispatcher::getEventName(StocktakeCountingProcessItemDefinition::ENTITY_NAME) => 'requestChangeSet',
            EntityPreWriteValidationEventDispatcher::getEventName(StocktakeCountingProcessDefinition::ENTITY_NAME) => 'requestChangeSet',
        ];
    }

    public function requestChangeSet($event): void
    {
        if (!($event instanceof EntityPreWriteValidationEvent)) {
            // The subscriber is probably instantiated in its old version (with the Shopware PreWriteValidationEvent) in
            // the container and will be updated on the next container rebuild (next request). Early return.
            return;
        }

        foreach ($event->getCommands() as $command) {
            if ($command instanceof ChangeSetAware) {
                $command->requestChangeSet();
            }
        }
    }

    public function warehouseStockUpdated(WarehouseStockUpdatedEvent $event): void
    {
        $productStocktakeCombinations = $this->connection->fetchAllAssociative(
            'SELECT
                HEX(countingProcessItem.`product_id`) as productId,
                HEX(stocktake.`id`) as stocktakeId
            FROM pickware_erp_stocktaking_stocktake stocktake
                LEFT JOIN pickware_erp_stocktaking_stocktake_counting_process countingProcess
                    ON stocktake.`id` = countingProcess.`stocktake_id`
                LEFT JOIN pickware_erp_stocktaking_stocktake_counting_process_item countingProcessItem
                    ON countingProcess.`id` = countingProcessItem.`counting_process_id`
            WHERE
                stocktake.`warehouse_id` IN (:warehouseIds)
                AND stocktake.`is_active` = 1
                AND countingProcessItem.`product_id` IN (:productIds)',
            [
                'warehouseIds' => array_map('hex2bin', $event->getWarehouseIds()),
                'productIds' => array_map('hex2bin', $event->getProductIds()),
            ],
            [
                'warehouseIds' => ArrayParameterType::STRING,
                'productIds' => ArrayParameterType::STRING,
            ],
        );

        $this->summaryCalculator->recalculateStocktakeProductSummaries(
            array_unique(array_column($productStocktakeCombinations, 'productId')),
            array_unique(array_column($productStocktakeCombinations, 'stocktakeId')),
        );
    }

    public function stocktakeCountingProcessItemWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $countingProcessItemIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            // An array primary key should not be possible (for this entity), but we assert it either way to ensure the
            // handling afterwards does not fault.
            if (!is_string($writeResult->getPrimaryKey())) {
                continue;
            }

            $countingProcessItemIds[] = $writeResult->getPrimaryKey();
        }

        if (count($countingProcessItemIds) === 0) {
            return;
        }

        $productStocktakeCombinations = $this->connection->fetchAllAssociative(
            'SELECT
                HEX(countingProcessItem.`product_id`) as productId,
                HEX(countingProcess.`stocktake_id`) as stocktakeId
            FROM `pickware_erp_stocktaking_stocktake_counting_process_item` countingProcessItem
                LEFT JOIN `pickware_erp_stocktaking_stocktake_counting_process` countingProcess
                    ON countingProcessItem.`counting_process_id` = countingProcess.`id`
                LEFT JOIN `pickware_erp_stocktaking_stocktake` stocktake
                    ON countingProcess.`stocktake_id` = stocktake.`id`
            WHERE
                  countingProcessItem.`id` IN (:countingProcessItemIds)
                  AND stocktake.`is_active` = 1',
            ['countingProcessItemIds' => array_map('hex2bin', $countingProcessItemIds)],
            ['countingProcessItemIds' => ArrayParameterType::STRING],
        );

        $this->summaryCalculator->recalculateStocktakeProductSummaries(
            array_unique(array_column($productStocktakeCombinations, 'productId')),
            array_unique(array_column($productStocktakeCombinations, 'stocktakeId')),
        );
    }

    public function stocktakeCountingProcessItemDeleted(EntityDeletedEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $deletedCountingProcessItemIds = [];
        $productIds = [];
        $countingProcessIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            // An array primary key should not be possible (for this entity), but we assert it either way to ensure the
            // handling afterwards does not fault.
            if (
                $writeResult->getOperation() !== EntityWriteResult::OPERATION_DELETE
                || !is_string($writeResult->getPrimaryKey())
            ) {
                continue;
            }

            $productId = $writeResult->getChangeSet()->getBefore('product_id');
            if ($productId === null) {
                continue;
            }
            $productIds[] = bin2hex($productId);
            $countingProcessIds[] = bin2hex($writeResult->getChangeSet()->getBefore('counting_process_id'));
            $deletedCountingProcessItemIds[] = $writeResult->getPrimaryKey();
        }

        if (count($deletedCountingProcessItemIds) === 0) {
            return;
        }

        $stocktakeIds = $this->connection->fetchAllAssociative(
            'SELECT HEX(countingProcess.`stocktake_id`) as stocktakeId
            FROM `pickware_erp_stocktaking_stocktake_counting_process` countingProcess
                LEFT JOIN `pickware_erp_stocktaking_stocktake` stocktake
                    ON countingProcess.`stocktake_id` = stocktake.`id`
            WHERE
                  countingProcess.`id` IN (:countingProcessIds)
                  AND stocktake.`is_active` = 1',
            ['countingProcessIds' => array_map('hex2bin', $countingProcessIds)],
            ['countingProcessIds' => ArrayParameterType::STRING],
        );

        // When a counting process item is deleted, recalculate all referenced product summaries without the counting
        // process items that are about to be deleted. This recalculation has to be done before the counting process
        // item is actually deleted, so we can determine the referenced products. This is why the deny-list is used.
        $this->summaryCalculator->recalculateStocktakeProductSummaries(
            $productIds,
            array_unique(array_column($stocktakeIds, 'stocktakeId')),
            $deletedCountingProcessItemIds,
        );
    }

    public function stocktakeCountingProcessWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $countingProcessIds = [];
        $additionalStocktakeIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            // An array primary key should not be possible (for this entity), but we assert it either way to ensure the
            // handling afterwards does not fault. Additionally, we do not handle DELETE operations here as they are
            // handled in the ::entityWritten method.
            if (
                !is_string($writeResult->getPrimaryKey())
                || $writeResult->getOperation() === EntityWriteResult::OPERATION_DELETE
            ) {
                continue;
            }

            $countingProcessIds[] = $writeResult->getPrimaryKey();

            $changeSet = $writeResult->getChangeSet();
            if ($changeSet && $changeSet->hasChanged('stocktake_id')) {
                // Recalculate the summaries for the old stocktake as it is now possibly missing some counted stock
                // (e.g. from the items of this counting process)
                $additionalStocktakeIds[] = bin2hex($changeSet->getBefore('stocktake_id'));
            }
        }

        if (count($countingProcessIds) === 0) {
            return;
        }

        $productStocktakeCombinations = $this->connection->fetchAllAssociative(
            'SELECT
                HEX(countingProcessItem.`product_id`) as productId,
                HEX(countingProcess.`stocktake_id`) as stocktakeId
            FROM `pickware_erp_stocktaking_stocktake_counting_process_item` countingProcessItem
                LEFT JOIN `pickware_erp_stocktaking_stocktake_counting_process` countingProcess
                    ON countingProcessItem.`counting_process_id` = countingProcess.`id`
                LEFT JOIN `pickware_erp_stocktaking_stocktake` stocktake
                    ON countingProcess.`stocktake_id` = stocktake.`id`
            WHERE
                  countingProcess.`id` IN (:countingProcessIds)
                  AND stocktake.`is_active` = 1',
            ['countingProcessIds' => array_map('hex2bin', $countingProcessIds)],
            ['countingProcessIds' => ArrayParameterType::STRING],
        );

        // Recalculate the summaries for the given products and stocktakes, but do not generate
        $this->summaryCalculator->recalculateStocktakeProductSummaries(
            array_unique(array_column($productStocktakeCombinations, 'productId')),
            array_unique(array_merge(
                array_column($productStocktakeCombinations, 'stocktakeId'),
                $additionalStocktakeIds,
            )),
        );
    }

    public function entityWritten(EntityWrittenContainerEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $deletedCountingProcessIds = $event->getDeletedPrimaryKeys(StocktakeCountingProcessDefinition::ENTITY_NAME);

        if (count($deletedCountingProcessIds) === 0) {
            return;
        }

        // As this event is dispatched only after the counting processes themselves were deleted which through foreign
        // keys also entails the counting process items, we cannot access the affected products and stocktakes with
        // their primary keys only. We thus have to fetch the affected product and stocktake ids from the item event
        // in the given container event.
        $countingProcessItemEvent = $event->getEventByEntityName(StocktakeCountingProcessItemDefinition::ENTITY_NAME);

        // Incase no counting process items were deleted with the counting process (e.g. it had none attached), we can
        // return early.
        if (!$countingProcessItemEvent) {
            return;
        }

        $productIds = [];
        $deletedCountingProcessItemIds = [];
        foreach ($countingProcessItemEvent->getWriteResults() as $writeResult) {
            // An array primary key should not be possible (for this entity), but we assert it either way to ensure the
            // handling afterwards does not fault.
            if (
                $writeResult->getOperation() !== EntityWriteResult::OPERATION_DELETE
                || !is_string($writeResult->getPrimaryKey())
            ) {
                continue;
            }

            // Only consider products of items that were deleted with a counting process. Single item deletion is
            // handled in ::stocktakeCountingProcessItemWritten()
            if (!in_array(bin2hex($writeResult->getChangeSet()->getBefore('counting_process_id')), $deletedCountingProcessIds)) {
                continue;
            }

            $productId = $writeResult->getChangeSet()->getBefore('product_id');
            if ($productId === null) {
                continue;
            }
            $productIds[] = bin2hex($productId);
            $deletedCountingProcessItemIds[] = $writeResult->getPrimaryKey();
        }

        $stocktakeCountingProcessEvent = $event->getEventByEntityName(StocktakeCountingProcessDefinition::ENTITY_NAME);
        $stocktakeIds = [];
        foreach ($stocktakeCountingProcessEvent->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_DELETE) {
                continue;
            }

            $stocktakeIds[] = bin2hex($writeResult->getChangeSet()->getBefore('stocktake_id'));
        }

        $this->summaryCalculator->recalculateStocktakeProductSummaries(
            $productIds,
            $stocktakeIds,
            $deletedCountingProcessItemIds,
        );
    }

    public function stocktakeWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $stocktakeIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            // An array primary key should not be possible (for this entity), but we assert it either way to ensure the
            // handling afterwards does not fault.
            if (!is_string($writeResult->getPrimaryKey())) {
                continue;
            }

            $changeSet = $writeResult->getChangeSet();

            // This entity written event is only relevant for when the stocktake was changed from inactive to active
            // (= setting the related importExportId from not-null to null). We need to recalculate all product
            // summaries for that stocktake in this case, as recalculation is skipped for inactive stocktakes and the
            // summaries may be out of date.
            if ($changeSet && (!$changeSet->hasChanged('import_export_id') || $changeSet->getAfter('import_export_id') !== null)) {
                continue;
            }

            $stocktakeIds[] = $writeResult->getPrimaryKey();
        }

        if (count($stocktakeIds) === 0) {
            return;
        }

        // We know that each stocktake (id) that made it thus far must be active, so this assertion is skipped in the
        // SQL statement
        $productStocktakeCombinations = $this->connection->fetchAllAssociative(
            'SELECT
                HEX(countingProcessItem.`product_id`) as productId,
                HEX(countingProcess.`stocktake_id`) as stocktakeId
            FROM `pickware_erp_stocktaking_stocktake_counting_process_item` countingProcessItem
                LEFT JOIN `pickware_erp_stocktaking_stocktake_counting_process` countingProcess
                    ON countingProcessItem.`counting_process_id` = countingProcess.`id`
            WHERE countingProcess.`stocktake_id` IN (:stocktakeIds)',
            ['stocktakeIds' => array_map('hex2bin', $stocktakeIds)],
            ['stocktakeIds' => ArrayParameterType::STRING],
        );

        $this->summaryCalculator->recalculateStocktakeProductSummaries(
            array_unique(array_column($productStocktakeCombinations, 'productId')),
            array_unique(array_column($productStocktakeCombinations, 'stocktakeId')),
        );
    }
}
