<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder;

use Pickware\DalBundle\EntityManager;
use Pickware\MoneyBundle\Currency;
use Pickware\MoneyBundle\MoneyValue;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderRefundDefinition;
use Pickware\ShopwareExtensionsBundle\OrderTransaction\OrderTransactionCollectionExtension;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class ReturnOrderRefundService
{
    private EntityManager $entityManager;
    private StateMachineRegistry $stateMachineRegistry;

    public function __construct(
        EntityManager $entityManager,
        StateMachineRegistry $stateMachineRegistry,
    ) {
        $this->entityManager = $entityManager;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * Creates a return order refund for the current price of the return order and its line items.
     * As for now, the payment method and currency of the return order's order is used for the new refund entity.
     */
    public function createRefundForReturnOrders(array $returnOrderIds, Context $context): void
    {
        $returnOrderIds = array_unique($returnOrderIds);
        /** @var ReturnOrderCollection $returnOrders */
        $returnOrders = $this->entityManager->findBy(
            ReturnOrderDefinition::class,
            ['id' => $returnOrderIds],
            $context,
            [
                'order.transactions.stateMachineState',
                'order.currency',
                'refund',
            ],
        );
        if (count($returnOrderIds) > $returnOrders->count()) {
            throw ReturnOrderException::returnOrderNotFound($returnOrderIds, $returnOrders->getKeys());
        }
        $initialReturnOrderRefundStateMachineStateId = $this->stateMachineRegistry->getStateMachine(
            ReturnOrderRefundStateMachine::TECHNICAL_NAME,
            $context,
        )->getInitialStateId();

        $refundPayloads = [];
        foreach ($returnOrders as $returnOrder) {
            // The return order already has a refund. We will not overwrite it here.
            if ($returnOrder->getRefund() !== null) {
                continue;
            }

            $transaction = OrderTransactionCollectionExtension::getPrimaryOrderTransaction(
                $returnOrder->getOrder()->getTransactions(),
            );
            if (!$transaction) {
                throw ReturnOrderException::noTransactionInOrder($returnOrder->getOrderId());
            }

            $refundPayloads[] = [
                'returnOrderId' => $returnOrder->getId(),
                'paymentMethodId' => $transaction->getPaymentMethodId(),
                'moneyValue' => new MoneyValue(
                    $returnOrder->getAmountTotal(),
                    new Currency($returnOrder->getOrder()->getCurrency()->getIsoCode()),
                ),
                'transactionInformationId' => null,
                'transactionInformation' => [],
                'stateId' => $initialReturnOrderRefundStateMachineStateId,
            ];
        }

        $this->entityManager->create(ReturnOrderRefundDefinition::class, $refundPayloads, $context);
    }
}
