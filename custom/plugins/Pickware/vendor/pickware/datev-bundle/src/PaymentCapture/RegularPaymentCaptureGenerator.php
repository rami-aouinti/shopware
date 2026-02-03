<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PaymentCapture;

use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Pickware\DatevBundle\Config\ConfigService;
use Pickware\DatevBundle\PickwareDatevBundle;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class RegularPaymentCaptureGenerator implements PaymentCaptureGenerator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ConfigService $configService,
        #[Autowire(param: 'pickware_datev.payment_capture.payment_capture_indexing_batch_size')]
        private readonly int $batchSize,
        private readonly RegularPaymentCaptureService $paymentCaptureService,
    ) {}

    public function supportsSalesChannelType(string $salesChannelTypeId): bool
    {
        return $this->paymentCaptureService->supportsSalesChannelType($salesChannelTypeId);
    }

    public function getProjectedPaymentCaptureCount(
        string $salesChannelId,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        DateTimeInterface $projectionDate,
        Context $context,
    ): int {
        $config = $this->configService->getConfig($salesChannelId, $context)->getValues();
        $excludedPaymentMethodIds = $config->getPaymentCapture()->getIdsOfExcludedPaymentMethods();

        $allowedToStates = array_merge(
            $config->getPaymentCapture()->getIdsOfOrderTransactionStatesForCaptureTypePayment(),
            $config->getPaymentCapture()->getIdsOfOrderTransactionStatesForCaptureTypeRefund(),
        );

        if (count($allowedToStates) === 0) {
            return 0;
        }

        return (int) $this->connection->fetchFirstColumn(
            'SELECT COUNT(*) as count
            FROM `order_transaction`
            JOIN `order`
                ON `order_transaction`.`order_id` = `order`.`id` AND `order_transaction`.`order_version_id` = `order`.`version_id`
            JOIN `sales_channel`
                ON `order`.`sales_channel_id` = `sales_channel`.`id`
            LEFT JOIN `state_machine_history` history
                ON history.`referenced_id` = `order_transaction`.`id` AND history.`referenced_version_id` = `order_transaction`.`version_id`
            LEFT JOIN `pickware_datev_payment_capture` transactionPaymentCapture
                ON transactionPaymentCapture.`order_transaction_id` = `order_transaction`.`id`
                       AND transactionPaymentCapture.`order_transaction_version_id` = `order_transaction`.`version_id`
                       AND transactionPaymentCapture.`state_machine_history_id` IS NULL
            LEFT JOIN `pickware_datev_payment_capture` historyPaymentCapture
                ON historyPaymentCapture.`order_transaction_id` = `order_transaction`.`id`
                       AND historyPaymentCapture.`order_transaction_version_id` = `order_transaction`.`version_id`
                       AND historyPaymentCapture.`state_machine_history_id` = history.`id`
            WHERE
                `sales_channel`.`id` = :salesChannelId
                AND `sales_channel`.`type_id` != :posSalesChannelTypeId
                AND (
                    (history.`id` IS NOT NULL AND historyPaymentCapture.`id` IS NULL) OR
                    (history.`id` IS NULL AND transactionPaymentCapture.`id` IS NULL)
                )
                ' . $this->getExcludePaymentMethodsCondition($excludedPaymentMethodIds) . '
                AND (
                    history.`to_state_id` IN (:allowedToStates)
                    OR (history.`id` IS NULL AND `order_transaction`.`state_id` IN (:allowedToStates))
                )
                AND (
                    history.`created_at` >= :startDate
                    OR (history.`id` IS NULL AND `order_transaction`.`created_at` >= :startDate)
                )
                AND (
                    history.`created_at` <= :endDate
                    OR (history.`id` IS NULL AND `order_transaction`.`created_at` <= :endDate)
                )
                AND (
                    history.`created_at` <= :projectionDate
                    OR (history.`id` IS NULL AND `order_transaction`.`created_at` <= :projectionDate)
                )
                AND `order_transaction`.`version_id` = :liveVersionId
            ORDER BY `order_transaction`.`created_at`, history.`created_at`',
            [
                'salesChannelId' => hex2bin($salesChannelId),
                'posSalesChannelTypeId' => hex2bin(PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID),
                'allowedToStates' => array_map('hex2bin', $allowedToStates),
                'excludedPaymentMethodIds' => array_map('hex2bin', $excludedPaymentMethodIds),
                'startDate' => $startDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'endDate' => $endDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'projectionDate' => $projectionDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'allowedToStates' => ArrayParameterType::STRING,
                'excludedPaymentMethodIds' => ArrayParameterType::STRING,
            ],
        )[0];
    }

    public function createNextPaymentCaptureBatch(
        string $salesChannelId,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        DateTimeInterface $projectionDate,
        Context $context,
    ): int {
        $config = $this->configService->getConfig($salesChannelId, $context)->getValues();
        $excludedPaymentMethodIds = $config->getPaymentCapture()->getIdsOfExcludedPaymentMethods();

        $allowedToStates = array_merge(
            $config->getPaymentCapture()->getIdsOfOrderTransactionStatesForCaptureTypePayment(),
            $config->getPaymentCapture()->getIdsOfOrderTransactionStatesForCaptureTypeRefund(),
        );

        if (count($allowedToStates) === 0) {
            return 0;
        }

        $result = $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(`order_transaction`.`id`)) as orderTransactionId,
                LOWER(HEX(history.`id`)) as stateMachineHistoryEntryId,
                LOWER(HEX(IFNULL(history.`to_state_id`, `order_transaction`.`state_id`))) as stateId
            FROM `order_transaction`
            JOIN `order`
                ON `order_transaction`.`order_id` = `order`.`id` AND `order_transaction`.`order_version_id` = `order`.`version_id`
            JOIN `sales_channel`
                ON `order`.`sales_channel_id` = `sales_channel`.`id`
            LEFT JOIN `state_machine_history` history
                ON history.`referenced_id` = `order_transaction`.`id` AND history.`referenced_version_id` = `order_transaction`.`version_id`
            LEFT JOIN `pickware_datev_payment_capture` transactionPaymentCapture
                ON transactionPaymentCapture.`order_transaction_id` = `order_transaction`.`id`
                       AND transactionPaymentCapture.`order_transaction_version_id` = `order_transaction`.`version_id`
                       AND transactionPaymentCapture.`state_machine_history_id` IS NULL
            LEFT JOIN `pickware_datev_payment_capture` historyPaymentCapture
                ON historyPaymentCapture.`order_transaction_id` = `order_transaction`.`id`
                       AND historyPaymentCapture.`order_transaction_version_id` = `order_transaction`.`version_id`
                       AND historyPaymentCapture.`state_machine_history_id` = history.`id`
            WHERE
                `sales_channel`.`id` = :salesChannelId
                AND `sales_channel`.`type_id` != :posSalesChannelTypeId
                AND (
                    (history.`id` IS NOT NULL AND historyPaymentCapture.`id` IS NULL) OR
                    (history.`id` IS NULL AND transactionPaymentCapture.`id` IS NULL)
                )
                ' . $this->getExcludePaymentMethodsCondition($excludedPaymentMethodIds) . '
                AND (
                    history.`to_state_id` IN (:allowedToStates)
                    OR (history.`id` IS NULL AND `order_transaction`.`state_id` IN (:allowedToStates))
                )
                AND (
                    history.`created_at` >= :startDate
                    OR (history.`id` IS NULL AND `order_transaction`.`created_at` >= :startDate)
                )
                AND (
                    history.`created_at` <= :endDate
                    OR (history.`id` IS NULL AND `order_transaction`.`created_at` <= :endDate)
                )
                AND (
                    history.`created_at` <= :projectionDate
                    OR (history.`id` IS NULL AND `order_transaction`.`created_at` <= :projectionDate)
                )
                AND `order_transaction`.`version_id` = :liveVersionId
            ORDER BY `order_transaction`.`created_at`, history.`created_at`
            LIMIT :batchSize',
            [
                'salesChannelId' => hex2bin($salesChannelId),
                'posSalesChannelTypeId' => hex2bin(PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID),
                'allowedToStates' => array_map('hex2bin', $allowedToStates),
                'excludedPaymentMethodIds' => array_map('hex2bin', $excludedPaymentMethodIds),
                'startDate' => $startDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'endDate' => $endDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'projectionDate' => $projectionDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'batchSize' => $this->batchSize,
            ],
            [
                'allowedToStates' => ArrayParameterType::STRING,
                'excludedPaymentMethodIds' => ArrayParameterType::STRING,
                'batchSize' => ParameterType::INTEGER,
            ],
        );

        $stateTransitionsByOrderTransactionId = [];
        foreach ($result as $row) {
            $orderTransactionId = $row['orderTransactionId'];
            $stateTransitionsByOrderTransactionId[$orderTransactionId] ??= [];
            $stateTransitionsByOrderTransactionId[$orderTransactionId][] = [
                'stateId' => $row['stateId'],
                'stateMachineHistoryEntryId' => $row['stateMachineHistoryEntryId'],
            ];
        }

        return $this->paymentCaptureService->capturePayments($stateTransitionsByOrderTransactionId, $context);
    }

    private function getExcludePaymentMethodsCondition(array $excludedPaymentMethodIds): string
    {
        if (count($excludedPaymentMethodIds) === 0) {
            return '';
        }

        return 'AND `order_transaction`.`payment_method_id` NOT IN (:excludedPaymentMethodIds)';
    }
}
