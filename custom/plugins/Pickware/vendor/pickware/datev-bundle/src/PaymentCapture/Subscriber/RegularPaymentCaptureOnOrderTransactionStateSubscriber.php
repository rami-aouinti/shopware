<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PaymentCapture\Subscriber;

use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\Config\ConfigService;
use Pickware\DatevBundle\Config\Values\ConfigValues;
use Pickware\DatevBundle\PaymentCapture\RegularPaymentCaptureService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RegularPaymentCaptureOnOrderTransactionStateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ConfigService $configService,
        private readonly RegularPaymentCaptureService $paymentCaptureService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_TRANSACTION_WRITTEN_EVENT => 'orderTransactionWritten',
            sprintf('state_machine.%s_changed', OrderTransactionStates::STATE_MACHINE) => 'orderTransactionStateChanged',
        ];
    }

    public function orderTransactionWritten(EntityWrittenEvent $event): void
    {
        $context = $event->getContext();
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        /** @var OrderTransactionCollection $orderTransactions */
        $orderTransactions = $this->entityManager->findBy(
            OrderTransactionDefinition::class,
            [
                'id' => array_map(
                    fn(EntityWriteResult $writeResult) => (string) $writeResult->getPrimaryKey(),
                    $event->getWriteResults(),
                ),
            ],
            $context,
            ['order'],
        );
        $salesChannelIds = $orderTransactions
            ->map(fn(OrderTransactionEntity $transaction) => $transaction->getOrder()->getSalesChannelId());

        $configsBySalesChannelIds = $this->getDatevConfigsBySalesChannelIds($salesChannelIds, $event->getContext());
        $stateTransitionsByOrderTransactionId = [];
        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_INSERT) {
                continue;
            }

            $orderTransactionId = (string) $writeResult->getPrimaryKey();
            $orderTransaction = $orderTransactions->get($orderTransactionId);
            $config = $configsBySalesChannelIds[$orderTransaction->getOrder()->getSalesChannelId()];
            if (!$config->getPaymentCapture()->isAutomaticPaymentCaptureEnabled()) {
                continue;
            }

            $stateTransitionsByOrderTransactionId[$orderTransactionId] = [
                [
                    'stateId' => $orderTransaction->getStateId(),
                    'stateMachineHistoryEntryId' => null,
                ],
            ];
        }

        $event->getContext()->scope(
            Context::SYSTEM_SCOPE,
            function(Context $context) use ($stateTransitionsByOrderTransactionId): void {
                $this->paymentCaptureService->capturePayments($stateTransitionsByOrderTransactionId, $context);
            },
        );
    }

    public function orderTransactionStateChanged(StateMachineStateChangeEvent $event): void
    {
        if (
            $event->getContext()->getVersionId() !== Defaults::LIVE_VERSION
            || $event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER
        ) {
            return;
        }

        // We cannot access the history entry from the event itself, so we assume the most recently written history
        // entry is the one related to the event.
        /** @var StateMachineHistoryEntity $stateMachineHistoryEntry */
        $stateMachineHistoryEntry = $this->entityManager->getOneBy(
            StateMachineHistoryDefinition::class,
            (new Criteria())
                ->addFilter(
                    new EqualsFilter('referencedId', $event->getTransition()->getEntityId()),
                    new EqualsFilter('referencedVersionId', $event->getContext()->getVersionId()),
                )
                ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING))
                ->setLimit(1),
            $event->getContext(),
        );

        /** @var OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->entityManager->getByPrimaryKey(
            OrderTransactionDefinition::class,
            $event->getTransition()->getEntityId(),
            $event->getContext(),
            ['order'],
        );
        $salesChannelId = $orderTransaction->getOrder()->getSalesChannelId();
        $config = $this->configService->getConfig($salesChannelId, $event->getContext())->getValues();
        if (!$config->getPaymentCapture()->isAutomaticPaymentCaptureEnabled()) {
            return;
        }

        $event->getContext()->scope(
            Context::SYSTEM_SCOPE,
            function(Context $context) use ($event, $stateMachineHistoryEntry): void {
                $this->paymentCaptureService->capturePayments([
                    $event->getTransition()->getEntityId() => [
                        [
                            'stateId' => $event->getNextState()->getId(),
                            'stateMachineHistoryEntryId' => $stateMachineHistoryEntry->getId(),
                        ],
                    ],
                ], $context);
            },
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
