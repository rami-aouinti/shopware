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

use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\Config\ConfigService;
use Pickware\DatevBundle\Config\Values\ConfigValues;
use Pickware\DatevBundle\PaymentCapture\Model\PaymentCaptureDefinition;
use Pickware\DatevBundle\PaymentCapture\Model\PaymentCaptureEntity;
use Pickware\DatevBundle\PickwareDatevBundle;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;

class RegularPaymentCaptureService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ConfigService $configService,
    ) {}

    public function supportsSalesChannelType(string $salesChannelTypeId): bool
    {
        return !in_array(
            $salesChannelTypeId,
            [
                // Payment captures related to POS are handled via the services in the PosPayment namespace
                PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID,
                // Payment captures from Shopify related orders are handled via external order transaction captures thrown
                // in the Shopify integration. Skip the automatic creation here entirely.
                PickwareDatevBundle::PICKWARE_SHOPIFY_INTEGRATION_SALES_CHANNEL_TYPE_ID,
            ],
            strict: true,
        );
    }

    /**
     * @param array<string, array<array{stateMachineHistoryEntryId: string|null, stateId: string}>> $stateTransitionsByOrderTransactionId
     */
    public function capturePayments(array $stateTransitionsByOrderTransactionId, Context $context): int
    {
        /** @var OrderTransactionCollection $orderTransactions */
        $orderTransactions = $this->entityManager->findBy(
            OrderTransactionDefinition::class,
            ['id' => array_keys($stateTransitionsByOrderTransactionId)],
            $context,
            ['order.salesChannel'],
        );

        $alreadyCreatedPaymentCaptures = $this->entityManager->findBy(
            PaymentCaptureDefinition::class,
            ['orderTransactionId' => $orderTransactions->getIds()],
            $context,
        );

        $filteredOrderTransactions = $orderTransactions->filter(
            fn(OrderTransactionEntity $orderTransaction) =>
                $this->supportsSalesChannelType($orderTransaction->getOrder()->getSalesChannel()->getTypeId()),
        );
        $filteredStateTransitionsByOrderTransactionId = array_filter(
            $stateTransitionsByOrderTransactionId,
            fn(string $orderTransactionId) => in_array($orderTransactionId, $filteredOrderTransactions->getKeys(), strict: true),
            ARRAY_FILTER_USE_KEY,
        );

        $stateMachineHistoryEntryIds = array_filter(
            array_map(
                fn(array $stateTransition) => $stateTransition['stateMachineHistoryEntryId'],
                array_reduce(
                    array_values($filteredStateTransitionsByOrderTransactionId),
                    fn(array $result, array $stateTransitions) => array_merge($result, $stateTransitions),
                    [],
                ),
            ),
        );
        /** @var StateMachineHistoryCollection $stateMachineHistoryEntries */
        $stateMachineHistoryEntries = $this->entityManager->findBy(
            StateMachineHistoryDefinition::class,
            ['id' => array_values($stateMachineHistoryEntryIds)],
            $context,
        );

        $paymentCapturePayloads = [];
        foreach ($filteredStateTransitionsByOrderTransactionId as $orderTransactionId => $stateTransitions) {
            /** @var OrderTransactionEntity $orderTransaction */
            $orderTransaction = $orderTransactions->get($orderTransactionId);

            $configValues = $this->configService
                ->getConfig($orderTransaction->getOrder()->getSalesChannelId(), $context)
                ->getValues();

            foreach ($stateTransitions as $stateTransition) {
                $capturedAmount = $this->getCapturedPaymentAmount(
                    $orderTransaction->getAmount()->getTotalPrice(),
                    $stateTransition['stateId'],
                    $orderTransaction->getPaymentMethodId(),
                    $configValues,
                );

                if (!$capturedAmount) {
                    continue;
                }

                $alreadyCreatedPaymentCapture = $alreadyCreatedPaymentCaptures->filter(
                    fn(PaymentCaptureEntity $paymentCapture) => $paymentCapture->getOrderTransactionId() === $orderTransactionId
                        && $paymentCapture->getAmount() === $capturedAmount,
                )->first();

                if ($alreadyCreatedPaymentCapture !== null) {
                    continue;
                }

                $paymentCapturePayload = [
                    'id' => Uuid::randomHex(),
                    'type' => PaymentCaptureDefinition::TYPE_AUTOMATIC,
                    'amount' => $capturedAmount,
                    'originalAmount' => $capturedAmount,
                    'transactionDate' => $orderTransaction->getCreatedAt(),
                    'currencyId' => $orderTransaction->getOrder()->getCurrencyId(),
                    'orderId' => $orderTransaction->getOrderId(),
                    'orderTransactionId' => $orderTransaction->getId(),
                ];

                $stateMachineHistoryEntry = $stateMachineHistoryEntries->get($stateTransition['stateMachineHistoryEntryId']);
                if ($stateMachineHistoryEntry) {
                    $paymentCapturePayload['stateMachineHistoryId'] = $stateMachineHistoryEntry->getId();
                    $paymentCapturePayload['transactionDate'] = $stateMachineHistoryEntry->getCreatedAt();
                }

                $paymentCapturePayloads[] = $paymentCapturePayload;
            }
        }

        $this->entityManager->create(
            PaymentCaptureDefinition::class,
            $paymentCapturePayloads,
            $context,
        );

        return count($paymentCapturePayloads);
    }

    private function getCapturedPaymentAmount(
        float $amount,
        string $enteredStateId,
        string $paymentMethodId,
        ConfigValues $configValues,
    ): ?float {
        $paymentCaptureConfig = $configValues->getPaymentCapture();

        if (in_array($paymentMethodId, $paymentCaptureConfig->getIdsOfExcludedPaymentMethods())) {
            return null;
        }

        if (in_array($enteredStateId, $paymentCaptureConfig->getIdsOfOrderTransactionStatesForCaptureTypePayment())) {
            return $amount;
        }
        if (in_array($enteredStateId, $paymentCaptureConfig->getIdsOfOrderTransactionStatesForCaptureTypeRefund())) {
            return -1 * $amount;
        }

        return null;
    }
}
