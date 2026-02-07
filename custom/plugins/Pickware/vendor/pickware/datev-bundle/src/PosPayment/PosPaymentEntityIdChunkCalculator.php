<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PosPayment;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\EntryBatch\DependencyInjection\EntityIdChunkCalculatorRegistry;
use Pickware\DatevBundle\EntryBatch\EntityIdChunkCalculator;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchExportConfig;
use Pickware\DatevBundle\PickwareDatevBundle;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwarePos\CashMovement\CashMovementProvider;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: EntityIdChunkCalculatorRegistry::DI_CONTAINER_TAG)]
class PosPaymentEntityIdChunkCalculator implements EntityIdChunkCalculator
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Connection $connection,
        private readonly ?CashMovementProvider $cashMovementProvider,
    ) {}

    public function getEntityIdCountForExportConfig(array $exportConfig, Context $context): PosPaymentEntityIdCount
    {
        $config = EntryBatchExportConfig::fromExportConfig($exportConfig);
        $posPaymentConfig = PosPaymentExportConfig::fromExportConfig($exportConfig);
        $paymentCaptureCount = (int) $this->connection->fetchFirstColumn(
            'SELECT
                COUNT(paymentCapture.`id`) AS `count`
            FROM `pickware_datev_payment_capture` paymentCapture
            JOIN `order`
                ON paymentCapture.`order_id` = `order`.`id` AND paymentCapture.`order_version_id` = `order`.`version_id`
            JOIN `sales_channel`
                ON `order`.`sales_channel_id` = `sales_channel`.`id`
            WHERE
                `sales_channel`.`type_id` = :posSalesChannelTypeId
                AND `sales_channel`.`id` = :salesChannelId
                AND paymentCapture.`transaction_date` >= :startDate
                AND paymentCapture.`transaction_date` <= :endDate;',
            [
                'posSalesChannelTypeId' => hex2bin(PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID),
                'startDate' => $config->startDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'endDate' => $config->endDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'salesChannelId' => hex2bin($config->salesChannelId),
            ],
        )[0];

        if ($posPaymentConfig->usePosDataModelAbstraction) {
            $cashMovementCount = $this->cashMovementProvider
                ->countCashMovements($config->salesChannelId, $config->startDate, $config->endDate, $context);
        } else {
            $cashMovementCount = 0;
        }

        return new PosPaymentEntityIdCount($paymentCaptureCount, $cashMovementCount);
    }

    public function getEntityIdChunkForExport(string $exportId, int $chunkSize, int $offset, Context $context): array
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $exportConfig = EntryBatchExportConfig::fromExportConfig($export->getConfig());
        $posPaymentConfig = PosPaymentExportConfig::fromExportConfig($export->getConfig());

        $paymentCaptureCount = $posPaymentConfig->entityIdCount->getPaymentCaptureCount();
        $paymentCaptureChunkStart = $offset;
        $paymentCapturesToFetch = $offset < $paymentCaptureCount ? min($chunkSize, $paymentCaptureCount - $paymentCaptureChunkStart) : 0;

        $idResult = [];
        if ($paymentCapturesToFetch > 0) {
            $paymentCaptureIdResult = $this->connection->fetchFirstColumn(
                'SELECT
                    paymentCapture.`id` as paymentCaptureId
                FROM `pickware_datev_payment_capture` paymentCapture
                JOIN `order`
                    ON paymentCapture.`order_id` = `order`.`id` AND paymentCapture.`order_version_id` = `order`.`version_id`
                JOIN `sales_channel`
                    ON `order`.`sales_channel_id` = `sales_channel`.`id`
                WHERE
                    `sales_channel`.`type_id` = :posSalesChannelTypeId
                    AND `sales_channel`.`id` = :salesChannelId
                    AND paymentCapture.`transaction_date` >= :startDate
                    AND paymentCapture.`transaction_date` <= :endDate
                    AND paymentCapture.`created_at` <= :exportDate
                ORDER BY paymentCapture.`transaction_date`
                LIMIT ' . $paymentCapturesToFetch . '
                OFFSET ' . $paymentCaptureChunkStart,
                [
                    'posSalesChannelTypeId' => hex2bin(PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID),
                    'startDate' => $exportConfig->startDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'endDate' => $exportConfig->endDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'exportDate' => $export->getCreatedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'salesChannelId' => hex2bin($exportConfig->salesChannelId),
                ],
            );

            $idResult = array_map('bin2hex', $paymentCaptureIdResult);
        }

        // Since we want to fill the chunks with as many entity ids as possible, we allow fetching order and return
        // order ids for the same chunk, in the case that available order ids are exhausted but our chunk size is not.
        // Calculate the base offset for the return orders and the remaining chunk size to be filled with return order ids.
        $cashMovementOffset = max($offset - $paymentCaptureCount, 0);
        $cashMovementsToFetch = max($chunkSize - count($idResult), 0);

        if ($posPaymentConfig->usePosDataModelAbstraction && $cashMovementsToFetch > 0) {
            $endDate = $exportConfig->endDate < $export->getCreatedAt() ? $exportConfig->endDate : $export->getCreatedAt();

            $cashMovementIdResult = $this->cashMovementProvider->getCashMovementIdentifiers(
                $exportConfig->salesChannelId,
                $exportConfig->startDate,
                $endDate,
                $cashMovementsToFetch,
                $cashMovementOffset,
                $context,
            );

            $idResult = array_merge($idResult, $cashMovementIdResult);
        }

        return $idResult;
    }

    public function getEntryBatchRecordCreatorTechnicalName(): string
    {
        return PosPaymentRecordCreator::TECHNICAL_NAME;
    }
}
