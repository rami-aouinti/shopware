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

use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Pickware\DatevBundle\Config\ConfigService;
use Pickware\DatevBundle\PaymentCapture\PaymentCaptureGenerator;
use Pickware\DatevBundle\PickwareDatevBundle;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderRefundStateMachine;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PosPaymentCaptureGenerator implements PaymentCaptureGenerator
{
    public function __construct(
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly Connection $connection,
        private readonly ConfigService $configService,
        #[Autowire(param: 'pickware_datev.pos_payment.payment_capture_indexing_batch_size')]
        private readonly int $batchSize,
        private readonly PosPaymentCaptureService $posPaymentCaptureService,
    ) {}

    public function supportsSalesChannelType(string $salesChannelTypeId): bool
    {
        return $this->posPaymentCaptureService->supportsSalesChannelType($salesChannelTypeId);
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

        $orderTransactionCount = (int) $this->connection->fetchFirstColumn(
            'SELECT COUNT(*) as count
            FROM `order_transaction`
            JOIN `order`
                ON `order_transaction`.`order_id` = `order`.`id` AND `order_transaction`.`order_version_id` = `order`.`version_id`
            JOIN `sales_channel`
                ON `order`.`sales_channel_id` = `sales_channel`.`id`
            LEFT JOIN `state_machine_history` history
                ON history.`referenced_id` = `order_transaction`.`id` AND history.`referenced_version_id` = `order_transaction`.`version_id`
            LEFT JOIN pickware_datev_payment_capture orderTransactionPaymentCapture
                ON orderTransactionPaymentCapture.`order_transaction_id` = `order_transaction`.`id`
                       AND orderTransactionPaymentCapture.`order_transaction_version_id` = `order_transaction`.`version_id`
                       AND orderTransactionPaymentCapture.`state_machine_history_id` IS NULL
            LEFT JOIN pickware_datev_payment_capture historyPaymentCapture
                ON historyPaymentCapture.`order_transaction_id` = `order_transaction`.`id`
                       AND historyPaymentCapture.`order_transaction_version_id` = `order_transaction`.`version_id`
                       AND historyPaymentCapture.`state_machine_history_id` = history.`id`
            WHERE
                `sales_channel`.`id` = :salesChannelId
                AND `sales_channel`.`type_id` = :posSalesChannelTypeId
                AND (
                    (history.`id` IS NOT NULL AND historyPaymentCapture.`id` IS NULL) OR
                    (history.`id` IS NULL AND orderTransactionPaymentCapture.`id` IS NULL)
                )
                ' . $this->getExcludePaymentMethodsCondition($excludedPaymentMethodIds, baseEntityReference: '`order_transaction`') . '
                AND (
                    history.`to_state_id` = :orderTransactionPaidStateId
                    OR (history.`id` IS NULL AND `order_transaction`.`state_id` = :orderTransactionPaidStateId)
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
                AND `order_transaction`.`version_id` = :liveVersionId',
            [
                'salesChannelId' => hex2bin($salesChannelId),
                'posSalesChannelTypeId' => hex2bin(PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID),
                'orderTransactionPaidStateId' => hex2bin($this->getOrderTransactionPaidStateId($context)),
                'excludedPaymentMethodIds' => array_map('hex2bin', $excludedPaymentMethodIds),
                'startDate' => $startDate->format(DateTimeInterface::RFC3339_EXTENDED),
                'endDate' => $endDate->format(DateTimeInterface::RFC3339_EXTENDED),
                'projectionDate' => $projectionDate->format(DateTimeInterface::RFC3339_EXTENDED),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'excludedPaymentMethodIds' => ArrayParameterType::STRING,
            ],
        )[0];

        $returnOrderRefundCount = (int) $this->connection->fetchFirstColumn(
            'SELECT COUNT(*) as count
            FROM `pickware_erp_return_order_refund` refund
            JOIN `pickware_erp_return_order` returnOrder
                ON refund.`return_order_id` = returnOrder.`id` AND refund.`return_order_version_id` = returnOrder.`version_id`
            JOIN `order`
                ON returnOrder.`order_id` = `order`.`id` AND returnOrder.`order_version_id` = `order`.`version_id`
            JOIN `sales_channel`
                ON `order`.`sales_channel_id` = `sales_channel`.`id`
            LEFT JOIN pickware_datev_payment_capture refundPaymentCapture
                ON refundPaymentCapture.`return_order_refund_id` = refund.`id`
            WHERE
                `sales_channel`.`id` = :salesChannelId
                AND `sales_channel`.`type_id` = :posSalesChannelTypeId
                AND refundPaymentCapture.`id` IS NULL
                ' . $this->getExcludePaymentMethodsCondition($excludedPaymentMethodIds, baseEntityReference: 'refund') . '
                AND refund.`state_id` = :returnOrderRefundRefundedStateId
                AND refund.`created_at` >= :startDate
                AND refund.`created_at` <= :endDate
                AND refund.`created_at` <= :projectionDate
                AND `order`.`version_id` = :liveVersionId',
            [
                'salesChannelId' => hex2bin($salesChannelId),
                'posSalesChannelTypeId' => hex2bin(PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID),
                'returnOrderRefundRefundedStateId' => hex2bin($this->getReturnOrderRefundRefundedStateId($context)),
                'excludedPaymentMethodIds' => array_map('hex2bin', $excludedPaymentMethodIds),
                'startDate' => $startDate->format(DateTimeInterface::RFC3339_EXTENDED),
                'endDate' => $endDate->format(DateTimeInterface::RFC3339_EXTENDED),
                'projectionDate' => $projectionDate->format(DateTimeInterface::RFC3339_EXTENDED),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'excludedPaymentMethodIds' => ArrayParameterType::STRING,
            ],
        )[0];

        return $orderTransactionCount + $returnOrderRefundCount;
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

        $orderTransactionRows = $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(`order_transaction`.`id`)) as orderTransactionId,
                LOWER(HEX(`order_transaction`.`state_id`)) as orderTransactionStateId,
                LOWER(HEX(history.`id`)) as stateMachineHistoryEntryId,
                LOWER(HEX(`sales_channel`.`id`)) as salesChannelId
            FROM `order_transaction`
            JOIN `order`
                ON `order_transaction`.`order_id` = `order`.`id` AND `order_transaction`.`order_version_id` = `order`.`version_id`
            JOIN `sales_channel`
                ON `order`.`sales_channel_id` = `sales_channel`.`id`
            LEFT JOIN `state_machine_history` history
                ON history.`referenced_id` = `order_transaction`.`id` AND history.`referenced_version_id` = `order_transaction`.`version_id`
            LEFT JOIN pickware_datev_payment_capture orderTransactionPaymentCapture
                ON orderTransactionPaymentCapture.`order_transaction_id` = `order_transaction`.`id`
                       AND orderTransactionPaymentCapture.`order_transaction_version_id` = `order_transaction`.`version_id`
                       AND orderTransactionPaymentCapture.`state_machine_history_id` IS NULL
            LEFT JOIN pickware_datev_payment_capture historyPaymentCapture
                ON historyPaymentCapture.`order_transaction_id` = `order_transaction`.`id`
                       AND historyPaymentCapture.`order_transaction_version_id` = `order_transaction`.`version_id`
                       AND historyPaymentCapture.`state_machine_history_id` = history.`id`
            WHERE
                `sales_channel`.`id` = :salesChannelId
                AND `sales_channel`.`type_id` = :posSalesChannelTypeId
                AND (
                    (history.`id` IS NOT NULL AND historyPaymentCapture.`id` IS NULL) OR
                    (history.`id` IS NULL AND orderTransactionPaymentCapture.`id` IS NULL)
                )
                ' . $this->getExcludePaymentMethodsCondition($excludedPaymentMethodIds, baseEntityReference: '`order_transaction`') . '
                AND (
                    history.`to_state_id` = :orderTransactionPaidStateId
                    OR (history.`id` IS NULL AND `order_transaction`.`state_id` = :orderTransactionPaidStateId)
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
                'orderTransactionPaidStateId' => hex2bin($this->getOrderTransactionPaidStateId($context)),
                'excludedPaymentMethodIds' => array_map('hex2bin', $excludedPaymentMethodIds),
                'startDate' => $startDate->format(DateTimeInterface::RFC3339_EXTENDED),
                'endDate' => $endDate->format(DateTimeInterface::RFC3339_EXTENDED),
                'projectionDate' => $projectionDate->format(DateTimeInterface::RFC3339_EXTENDED),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'batchSize' => $this->batchSize,
            ],
            [
                'excludedPaymentMethodIds' => ArrayParameterType::STRING,
                'batchSize' => ParameterType::INTEGER,
            ],
        );

        $returnOrderRefundRows = $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(refund.`id`)) as returnOrderRefundId,
                LOWER(HEX(refund.`state_id`)) as returnOrderRefundStateId,
                LOWER(HEX(`sales_channel`.`id`)) as salesChannelId
            FROM `pickware_erp_return_order_refund` refund
            JOIN `pickware_erp_return_order` returnOrder
                ON refund.`return_order_id` = returnOrder.`id` AND refund.`return_order_version_id` = returnOrder.`version_id`
            JOIN `order`
                ON returnOrder.`order_id` = `order`.`id` AND returnOrder.`order_version_id` = `order`.`version_id`
            JOIN `sales_channel`
                ON `order`.`sales_channel_id` = `sales_channel`.`id`
            LEFT JOIN pickware_datev_payment_capture refundPaymentCapture
                ON refundPaymentCapture.`return_order_refund_id` = refund.`id`
            WHERE
                `sales_channel`.`id` = :salesChannelId
                AND `sales_channel`.`type_id` = :posSalesChannelTypeId
                AND refundPaymentCapture.`id` IS NULL
                ' . $this->getExcludePaymentMethodsCondition($excludedPaymentMethodIds, baseEntityReference: 'refund') . '
                AND refund.`state_id` = :returnOrderRefundRefundedStateId
                AND refund.`created_at` >= :startDate
                AND refund.`created_at` <= :endDate
                AND refund.`created_at` <= :projectionDate
                AND `order`.`version_id` = :liveVersionId
            ORDER BY refund.`created_at`
            LIMIT :batchSize',
            [
                'salesChannelId' => hex2bin($salesChannelId),
                'posSalesChannelTypeId' => hex2bin(PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID),
                'returnOrderRefundRefundedStateId' => hex2bin($this->getReturnOrderRefundRefundedStateId($context)),
                'excludedPaymentMethodIds' => array_map('hex2bin', $excludedPaymentMethodIds),
                'startDate' => $startDate->format(DateTimeInterface::RFC3339_EXTENDED),
                'endDate' => $endDate->format(DateTimeInterface::RFC3339_EXTENDED),
                'projectionDate' => $projectionDate->format(DateTimeInterface::RFC3339_EXTENDED),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'batchSize' => $this->batchSize,
            ],
            [
                'excludedPaymentMethodIds' => ArrayParameterType::STRING,
                'batchSize' => ParameterType::INTEGER,
            ],
        );

        $drafts = [];
        foreach ($orderTransactionRows as $row) {
            $drafts[] = new PosPaymentCaptureDraft(
                salesChannelId: $row['salesChannelId'],
                stateId: $row['orderTransactionStateId'],
                orderTransactionId: $row['orderTransactionId'],
                stateMachineHistoryEntryId: $row['stateMachineHistoryEntryId'],
                returnOrderRefundId: null,
            );
        }
        foreach ($returnOrderRefundRows as $row) {
            $drafts[] = new PosPaymentCaptureDraft(
                salesChannelId: $row['salesChannelId'],
                stateId: $row['returnOrderRefundStateId'],
                orderTransactionId: null,
                stateMachineHistoryEntryId: null,
                returnOrderRefundId: $row['returnOrderRefundId'],
            );
        }

        return $this->posPaymentCaptureService->capturePayments($drafts, $context);
    }

    private function getExcludePaymentMethodsCondition(array $excludedPaymentMethodIds, string $baseEntityReference): string
    {
        if (count($excludedPaymentMethodIds) === 0) {
            return '';
        }

        return sprintf('AND %s.`payment_method_id` NOT IN (:excludedPaymentMethodIds)', $baseEntityReference);
    }

    private function getOrderTransactionPaidStateId(Context $context): string
    {
        $orderTransactionStateMachine = $this->stateMachineRegistry->getStateMachine(OrderTransactionStates::STATE_MACHINE, $context);

        return $orderTransactionStateMachine->getStates()
            ->firstWhere(fn(StateMachineStateEntity $state) => $state->getTechnicalName() === OrderTransactionStates::STATE_PAID)
            ->getId();
    }

    private function getReturnOrderRefundRefundedStateId(Context $context): string
    {
        $returnOrderRefundStateMachine = $this->stateMachineRegistry->getStateMachine(ReturnOrderRefundStateMachine::TECHNICAL_NAME, $context);

        return $returnOrderRefundStateMachine->getStates()
            ->firstWhere(fn(StateMachineStateEntity $state) => $state->getTechnicalName() === ReturnOrderRefundStateMachine::STATE_REFUNDED)
            ->getId();
    }
}
