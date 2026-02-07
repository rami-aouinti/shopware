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

use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\Config\ConfigService;
use Pickware\DatevBundle\Config\Values\ConfigValues;
use Pickware\DatevBundle\PaymentCapture\Model\PaymentCaptureDefinition;
use Pickware\DatevBundle\PickwareDatevBundle;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderRefundCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderRefundDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderRefundStateMachine;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class PosPaymentCaptureService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly ConfigService $configService,
    ) {}

    public function supportsSalesChannelType(string $salesChannelTypeId): bool
    {
        return $salesChannelTypeId === PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID;
    }

    /**
     * @param PosPaymentCaptureDraft[] $paymentCaptureDrafts
     */
    public function capturePayments(array $paymentCaptureDrafts, Context $context): int
    {
        $transactionCaptureDrafts = array_filter(
            $paymentCaptureDrafts,
            fn(PosPaymentCaptureDraft $draft) => $draft->getOrderTransactionId() !== null,
        );
        $transactionCapturePayloads = [];
        if (count($transactionCaptureDrafts) > 0) {
            $transactionCapturePayloads = $this->createOrderTransactionPaymentCapturePayloads($transactionCaptureDrafts, $context);
        }

        $refundCaptureDrafts = array_filter(
            $paymentCaptureDrafts,
            fn(PosPaymentCaptureDraft $draft) => $draft->getReturnOrderRefundId() !== null,
        );
        $refundCapturePayloads = [];
        if (count($refundCaptureDrafts) > 0) {
            $refundCapturePayloads = $this->createReturnOrderRefundPaymentCapturePayloads($refundCaptureDrafts, $context);
        }

        $payloads = [
            ...$transactionCapturePayloads,
            ...$refundCapturePayloads,
        ];

        $this->entityManager->create(PaymentCaptureDefinition::class, $payloads, $context);

        return count($payloads);
    }

    /**
     * @param PosPaymentCaptureDraft[] $paymentCaptureDrafts
     */
    private function createOrderTransactionPaymentCapturePayloads(array $paymentCaptureDrafts, Context $context): array
    {
        $orderTransactionIds = array_map(
            fn(PosPaymentCaptureDraft $draft) => $draft->getOrderTransactionId(),
            $paymentCaptureDrafts,
        );

        /** @var OrderTransactionCollection $orderTransactions */
        $orderTransactions = $this->entityManager->findBy(
            OrderTransactionDefinition::class,
            (new Criteria($orderTransactionIds))
                ->addFilter(new EqualsFilter('order.salesChannel.typeId', PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID)),
            $context,
            ['order'],
        );

        $stateMachineHistoryEntryIds = array_filter(array_map(
            fn(PosPaymentCaptureDraft $draft) => $draft->getStateMachineHistoryEntryId(),
            $paymentCaptureDrafts,
        ));
        if (count($stateMachineHistoryEntryIds) > 0) {
            /** @var StateMachineHistoryCollection $stateMachineHistoryEntries */
            $stateMachineHistoryEntries = $this->entityManager->findBy(
                StateMachineHistoryDefinition::class,
                ['id' => $stateMachineHistoryEntryIds],
                $context,
            );
        } else {
            $stateMachineHistoryEntries = new StateMachineHistoryCollection();
        }

        $paidStateId = $this->getOrderTransactionPaidStateId($context);
        $configsBySalesChannelId = $this->getDatevConfigsBySalesChannelId($paymentCaptureDrafts, $context);
        $paymentCapturePayloads = [];
        foreach ($paymentCaptureDrafts as $draft) {
            $config = $configsBySalesChannelId[$draft->getSalesChannelId()];
            $excludedPaymentMethodIds = $config->getPaymentCapture()->getIdsOfExcludedPaymentMethods();

            if ($draft->getStateId() !== $paidStateId) {
                continue;
            }

            $orderTransaction = $orderTransactions->get($draft->getOrderTransactionId());
            if (!$orderTransaction || in_array($orderTransaction->getPaymentMethodId(), $excludedPaymentMethodIds, strict: true)) {
                continue;
            }

            $capturedAmount = $orderTransaction->getAmount()->getTotalPrice();
            $paymentCapturePayload = [
                'id' => Uuid::randomHex(),
                'type' => PaymentCaptureDefinition::TYPE_AUTOMATIC,
                'amount' => $capturedAmount,
                'originalAmount' => $capturedAmount,
                'transactionDate' => $orderTransaction->getCreatedAt(),
                'currencyId' => $orderTransaction->getOrder()->getCurrencyId(),
                'orderId' => $orderTransaction->getOrderId(),
                'orderTransactionId' => $orderTransaction->getId(),
                'transactionReference' => $orderTransaction->getCustomFieldsValue('pickwarePosTransactionId'),
            ];

            $stateMachineHistoryEntry = $stateMachineHistoryEntries->get($draft->getStateMachineHistoryEntryId());
            if ($stateMachineHistoryEntry) {
                $paymentCapturePayload['stateMachineHistoryId'] = $stateMachineHistoryEntry->getId();
                $paymentCapturePayload['transactionDate'] = $stateMachineHistoryEntry->getCreatedAt();
            }

            $paymentCapturePayloads[] = $paymentCapturePayload;
        }

        return $paymentCapturePayloads;
    }

    /**
     * @param PosPaymentCaptureDraft[] $paymentCaptureDrafts
     */
    private function createReturnOrderRefundPaymentCapturePayloads(array $paymentCaptureDrafts, Context $context): array
    {
        $returnOrderRefundIds = array_map(fn(PosPaymentCaptureDraft $draft) => $draft->getReturnOrderRefundId(), $paymentCaptureDrafts);

        /** @var ReturnOrderRefundCollection $returnOrderRefunds */
        $returnOrderRefunds = $this->entityManager->findBy(
            ReturnOrderRefundDefinition::class,
            (new Criteria($returnOrderRefundIds))
                ->addFilter(new EqualsFilter('returnOrder.order.salesChannel.typeId', PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID)),
            $context,
            ['returnOrder.order'],
        );

        $refundedStateId = $this->getReturnOrderRefundRefundedStateId($context);
        $configsBySalesChannelId = $this->getDatevConfigsBySalesChannelId($paymentCaptureDrafts, $context);
        /** @var array<string, CurrencyEntity|null> $currenciesByIsoCode */
        $currenciesByIsoCode = [];
        $paymentCapturePayloads = [];
        foreach ($paymentCaptureDrafts as $draft) {
            $config = $configsBySalesChannelId[$draft->getSalesChannelId()];
            $excludedPaymentMethodIds = $config->getPaymentCapture()->getIdsOfExcludedPaymentMethods();

            if ($draft->getStateId() !== $refundedStateId) {
                continue;
            }

            $returnOrderRefund = $returnOrderRefunds->get($draft->getReturnOrderRefundId());
            if (!$returnOrderRefund || in_array($returnOrderRefund->getPaymentMethodId(), $excludedPaymentMethodIds, strict: true)) {
                continue;
            }

            if (!array_key_exists($returnOrderRefund->getCurrencyIsoCode(), $currenciesByIsoCode)) {
                $currenciesByIsoCode[$returnOrderRefund->getCurrencyIsoCode()] = $this->entityManager->findOneBy(
                    CurrencyDefinition::class,
                    ['isoCode' => $returnOrderRefund->getCurrencyIsoCode()],
                    $context,
                );
            }

            $paymentCapturePayloads[] = [
                'id' => Uuid::randomHex(),
                'type' => PaymentCaptureDefinition::TYPE_AUTOMATIC,
                'amount' => -1 * $returnOrderRefund->getAmount(),
                'originalAmount' => -1 * $returnOrderRefund->getAmount(),
                'transactionDate' => $returnOrderRefund->getCreatedAt(),
                'currencyId' => $currenciesByIsoCode[$returnOrderRefund->getCurrencyIsoCode()]?->getId(),
                'orderId' => $returnOrderRefund->getReturnOrder()->getOrderId(),
                'returnOrderRefundId' => $returnOrderRefund->getId(),
                'transactionReference' => $returnOrderRefund->getTransactionId(),
            ];
        }

        return $paymentCapturePayloads;
    }

    /**
     * @param PosPaymentCaptureDraft[] $paymentCaptureDrafts
     * @return array<string, ConfigValues>
     */
    private function getDatevConfigsBySalesChannelId(array $paymentCaptureDrafts, Context $context): array
    {
        $salesChannelIds = array_map(fn(PosPaymentCaptureDraft $draft) => $draft->getSalesChannelId(), $paymentCaptureDrafts);
        $configsBySalesChannelId = [];
        foreach ($salesChannelIds as $salesChannelId) {
            $configsBySalesChannelId[$salesChannelId] = $this->configService->getConfig($salesChannelId, $context)->getValues();
        }

        return $configsBySalesChannelId;
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
