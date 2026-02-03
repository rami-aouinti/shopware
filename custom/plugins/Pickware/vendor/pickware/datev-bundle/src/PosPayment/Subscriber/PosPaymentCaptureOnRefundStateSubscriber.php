<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PosPayment\Subscriber;

use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\Config\ConfigService;
use Pickware\DatevBundle\Config\Values\ConfigValues;
use Pickware\DatevBundle\PosPayment\PosPaymentCaptureDraft;
use Pickware\DatevBundle\PosPayment\PosPaymentCaptureService;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderRefundCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderRefundDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderRefundEntity;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderRefundStateMachine;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PosPaymentCaptureOnRefundStateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ConfigService $configService,
        private readonly PosPaymentCaptureService $paymentCaptureService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ReturnOrderRefundDefinition::ENTITY_WRITTEN_EVENT => 'returnOrderRefundWritten',
            sprintf('state_machine.%s_changed', ReturnOrderRefundStateMachine::TECHNICAL_NAME) => 'returnOrderRefundStateChanged',
        ];
    }

    public function returnOrderRefundWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $returnOrderRefundIds = array_map(
            fn(EntityWriteResult $writeResult) => (string) $writeResult->getPrimaryKey(),
            array_filter(
                $event->getWriteResults(),
                fn(EntityWriteResult $writeResult) => $writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT,
            ),
        );
        /** @var ReturnOrderRefundCollection $returnOrderRefunds */
        $returnOrderRefunds = $this->entityManager->findBy(
            ReturnOrderRefundDefinition::class,
            ['id' => $returnOrderRefundIds],
            $event->getContext(),
            ['returnOrder.order'],
        );
        $salesChannelIds = $returnOrderRefunds
            ->map(fn(ReturnOrderRefundEntity $refund) => $refund->getReturnOrder()->getOrder()->getSalesChannelId());

        $configsBySalesChannelIds = $this->getDatevConfigsBySalesChannelIds($salesChannelIds, $event->getContext());
        $drafts = [];
        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_INSERT) {
                continue;
            }

            /** @var ReturnOrderRefundEntity $returnOrderRefund */
            $returnOrderRefund = $returnOrderRefunds->get((string) $writeResult->getPrimaryKey());
            $salesChannelId = $returnOrderRefund->getReturnOrder()->getOrder()->getSalesChannelId();

            if (!$configsBySalesChannelIds[$salesChannelId]->getPaymentCapture()->isAutomaticPaymentCaptureEnabled()) {
                continue;
            }

            $drafts[] = new PosPaymentCaptureDraft(
                salesChannelId: $salesChannelId,
                stateId: $returnOrderRefund->getStateId(),
                orderTransactionId: null,
                stateMachineHistoryEntryId: null,
                returnOrderRefundId: $returnOrderRefund->getId(),
            );
        }

        $event->getContext()->scope(
            Context::SYSTEM_SCOPE,
            fn(Context $context) => $this->paymentCaptureService->capturePayments($drafts, $context),
        );
    }

    public function returnOrderRefundStateChanged(StateMachineStateChangeEvent $event): void
    {
        if (
            $event->getContext()->getVersionId() !== Defaults::LIVE_VERSION
            || $event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER
        ) {
            return;
        }

        /** @var ReturnOrderRefundEntity $returnOrderRefund */
        $returnOrderRefund = $this->entityManager->getByPrimaryKey(
            ReturnOrderRefundDefinition::class,
            $event->getTransition()->getEntityId(),
            $event->getContext(),
            ['returnOrder.order'],
        );

        $salesChannelId = $returnOrderRefund->getReturnOrder()->getOrder()->getSalesChannelId();
        $config = $this->configService->getConfig($salesChannelId, $event->getContext())->getValues();
        if (!$config->getPaymentCapture()->isAutomaticPaymentCaptureEnabled()) {
            return;
        }

        $event->getContext()->scope(
            Context::SYSTEM_SCOPE,
            fn(Context $context) => $this->paymentCaptureService->capturePayments(
                [
                    new PosPaymentCaptureDraft(
                        salesChannelId: $salesChannelId,
                        stateId: $event->getNextState()->getId(),
                        orderTransactionId: null,
                        stateMachineHistoryEntryId: null,
                        returnOrderRefundId: $returnOrderRefund->getId(),
                    ),
                ],
                $context,
            ),
        );
    }

    /**
     * @param string[] $salesChannelIds
     * @return ConfigValues[]
     */
    private function getDatevConfigsBySalesChannelIds(array $salesChannelIds, Context $context): array
    {
        $configsBySalesChannelId = [];
        foreach ($salesChannelIds as $salesChannelId) {
            $configsBySalesChannelId[$salesChannelId] = $this->configService->getConfig($salesChannelId, $context)->getValues();
        }

        return $configsBySalesChannelId;
    }
}
