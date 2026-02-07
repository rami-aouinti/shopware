<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Payment;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\EntryBatch\DependencyInjection\EntityIdChunkCalculatorRegistry;
use Pickware\DatevBundle\EntryBatch\EntityIdChunkCalculator;
use Pickware\DatevBundle\EntryBatch\EntityIdCount;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchExportConfig;
use Pickware\DatevBundle\EntryBatch\SingleEntityIdCount;
use Pickware\DatevBundle\PickwareDatevBundle;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: EntityIdChunkCalculatorRegistry::DI_CONTAINER_TAG)]
class PaymentEntityIdChunkCalculator implements EntityIdChunkCalculator
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Connection $connection,
    ) {}

    public function getEntityIdCountForExportConfig(array $exportConfig, Context $context): EntityIdCount
    {
        $config = EntryBatchExportConfig::fromExportConfig($exportConfig);

        $countResult = $this->connection->fetchAssociative(
            'SELECT
                COUNT(pc.`id`) AS `count`
            FROM `pickware_datev_payment_capture` pc
            JOIN `order` o
                ON pc.`order_id` = o.`id` AND pc.`order_version_id` = o.`version_id`
            JOIN `sales_channel` sc
                ON o.`sales_channel_id` = sc.`id`
            WHERE
                NOT sc.`type_id` = :posSalesChannelTypeId
                AND sc.`id` = :salesChannelId
                AND pc.`transaction_date` >= :startDate
                AND pc.`transaction_date` <= :endDate;',
            [
                'posSalesChannelTypeId' => hex2bin(PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID),
                'startDate' => $config->startDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'endDate' => $config->endDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'salesChannelId' => hex2bin($config->salesChannelId),
            ],
        );

        return new SingleEntityIdCount((int) $countResult['count']);
    }

    public function getEntityIdChunkForExport(string $exportId, int $chunkSize, int $offset, Context $context): array
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $exportConfig = EntryBatchExportConfig::fromExportConfig($export->getConfig());

        $paymentCaptureIdResult = $this->connection->fetchAllAssociative(
            'SELECT
                pc.`id` as paymentCaptureId
            FROM `pickware_datev_payment_capture` pc
            JOIN `order` o
                ON pc.`order_id` = o.`id` AND pc.`order_version_id` = o.`version_id`
            JOIN `sales_channel` sc
                ON o.`sales_channel_id` = sc.`id`
            WHERE
                NOT sc.`type_id` = :posSalesChannelTypeId
                AND sc.`id` = :salesChannelId
                AND pc.`transaction_date` >= :startDate
                AND pc.`transaction_date` <= :endDate
                AND pc.`created_at` <= :exportDate
            ORDER BY pc.`transaction_date`, pc.`id`
            LIMIT ' . $chunkSize . '
            OFFSET ' . $offset,
            [
                'posSalesChannelTypeId' => hex2bin(PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID),
                'startDate' => $exportConfig->startDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'endDate' => $exportConfig->endDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'exportDate' => $export->getCreatedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'salesChannelId' => hex2bin($exportConfig->salesChannelId),
            ],
        );

        return array_map(fn($row) => bin2hex($row['paymentCaptureId']), $paymentCaptureIdResult);
    }

    public function getEntryBatchRecordCreatorTechnicalName(): string
    {
        return PaymentRecordCreator::TECHNICAL_NAME;
    }
}
