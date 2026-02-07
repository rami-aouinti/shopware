<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PosAccountingDocument;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\EntryBatch\DependencyInjection\EntityIdChunkCalculatorRegistry;
use Pickware\DatevBundle\EntryBatch\EntityIdChunkCalculator;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchExportConfig;
use Pickware\DatevBundle\PickwareDatevBundle;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: EntityIdChunkCalculatorRegistry::DI_CONTAINER_TAG)]
class PosAccountingDocumentEntityIdChunkCalculator implements EntityIdChunkCalculator
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Connection $connection,
    ) {}

    public function getEntityIdCountForExportConfig(array $exportConfig, Context $context): PosAccountingDocumentEntityIdCount
    {
        $entryBatchConfig = EntryBatchExportConfig::fromExportConfig($exportConfig);

        $orderCountResult = (int) $this->connection->fetchFirstColumn(
            'SELECT COUNT(o.`id`) as `count` FROM `order` o
            LEFT JOIN `sales_channel` sc ON o.`sales_channel_id` = sc.`id`
            WHERE sc.`type_id` = :posSalesChannelTypeId
                AND sc.`id` = :salesChannelId
                AND o.`created_at` >= :startDate
                AND o.`created_at` <= :endDate
                AND o.`version_id` = :liveVersionId;',
            [
                'startDate' => $entryBatchConfig->startDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'endDate' => $entryBatchConfig->endDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'posSalesChannelTypeId' => hex2bin(PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID),
                'salesChannelId' => hex2bin($entryBatchConfig->salesChannelId),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
        )[0];

        $returnOrderCountResult = (int) $this->connection->fetchFirstColumn(
            'SELECT COUNT(o.`id`) as `count` FROM `pickware_erp_return_order` ro
            INNER JOIN `order` o
                ON ro.`order_id` = o.`id` AND ro.`order_version_id` = o.`version_id`
            LEFT JOIN `sales_channel` sc ON o.`sales_channel_id` = sc.`id`
            WHERE sc.`type_id` = :posSalesChannelTypeId
                AND sc.`id` = :salesChannelId
                AND ro.`created_at` >= :startDate
                AND ro.`created_at` <= :endDate
                AND ro.`version_id` = :liveVersionId;',
            [
                'startDate' => $entryBatchConfig->startDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'endDate' => $entryBatchConfig->endDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'posSalesChannelTypeId' => hex2bin(PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID),
                'salesChannelId' => hex2bin($entryBatchConfig->salesChannelId),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
        )[0];

        return new PosAccountingDocumentEntityIdCount(
            $orderCountResult,
            $returnOrderCountResult,
        );
    }

    /**
     * @return string[]
     */
    public function getEntityIdChunkForExport(string $exportId, int $chunkSize, int $offset, Context $context): array
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $entryBatchConfig = EntryBatchExportConfig::fromExportConfig($export->getConfig());
        $posAccountingDocumentConfig = PosAccountingDocumentExportConfig::fromExportConfig($export->getConfig());

        $orderCount = $posAccountingDocumentConfig->entityIdCount->getOrderCount();
        $orderOffset = $offset;
        $ordersToFetch = $offset < $orderCount ? min($chunkSize, $orderCount - $orderOffset) : 0;

        $idResult = [];
        if ($ordersToFetch > 0) {
            $orderIds = $this->connection->fetchFirstColumn(
                'SELECT
                o.`id` as `orderId`
            FROM `order` o
            LEFT JOIN `sales_channel` sc ON o.`sales_channel_id` = sc.`id`
            WHERE sc.`type_id` = :posSalesChannelTypeId
                AND sc.`id` = :salesChannelId
                AND o.`created_at` >= :startDate
                AND o.`created_at` <= :endDate
                AND o.`created_at` <= :exportDate
                AND o.`version_id` = :lifeVersionId
            ORDER BY o.`created_at`, o.`id`
            LIMIT ' . $ordersToFetch . '
            OFFSET ' . $orderOffset,
                [
                    'startDate' => $entryBatchConfig->startDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'endDate' => $entryBatchConfig->endDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'exportDate' => $export->getCreatedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'posSalesChannelTypeId' => hex2bin(PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID),
                    'salesChannelId' => hex2bin($entryBatchConfig->salesChannelId),
                    'lifeVersionId' => hex2bin(Defaults::LIVE_VERSION),
                ],
            );

            $idResult = array_map(fn($orderId) => bin2hex($orderId), $orderIds);
        }

        // Since we want to fill the chunks with as many entity ids as possible, we allow fetching order and return
        // order ids for the same chunk, in the case that available order ids are exhausted but our chunk size is not.
        // Calculate the base offset for the return orders and the remaining chunk size to be filled with return order ids.
        $returnOrderOffset = max($offset - $orderCount, 0);
        $returnOrdersToFetch = max($chunkSize - count($idResult), 0);

        if ($returnOrderOffset >= 0 && $returnOrdersToFetch > 0) {
            $returnOrderIds = $this->connection->fetchFirstColumn(
                'SELECT ro.`id` as `returnOrderId`
            FROM `pickware_erp_return_order` ro
            INNER JOIN `order` o
                ON ro.`order_id` = o.`id` AND ro.`order_version_id` = o.`version_id`
            LEFT JOIN `sales_channel` sc ON o.`sales_channel_id` = sc.`id`
            WHERE sc.`type_id` = :posSalesChannelTypeId
                AND sc.`id` = :salesChannelId
                AND ro.`created_at` >= :startDate
                AND ro.`created_at` <= :endDate
                AND ro.`created_at` <= :exportDate
                AND ro.`version_id` = :lifeVersionId
            ORDER BY ro.`created_at`, ro.`id`
            LIMIT ' . $returnOrdersToFetch . '
            OFFSET ' . $returnOrderOffset,
                [
                    'startDate' => $entryBatchConfig->startDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'endDate' => $entryBatchConfig->endDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'exportDate' => $export->getCreatedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'posSalesChannelTypeId' => hex2bin(PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID),
                    'salesChannelId' => hex2bin($entryBatchConfig->salesChannelId),
                    'lifeVersionId' => hex2bin(Defaults::LIVE_VERSION),
                ],
            );

            $idResult = array_merge($idResult, array_map(fn($returnOrderId) => bin2hex($returnOrderId), $returnOrderIds));
        }

        return $idResult;
    }

    public function getEntryBatchRecordCreatorTechnicalName(): string
    {
        return PosAccountingDocumentRecordCreator::TECHNICAL_NAME;
    }
}
