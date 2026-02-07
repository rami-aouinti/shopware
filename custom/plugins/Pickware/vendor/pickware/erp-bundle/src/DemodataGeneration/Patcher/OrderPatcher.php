<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemodataGeneration\Patcher;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class OrderPatcher
{
    private EntityManager $entityManager;
    private NumberRangeValueGeneratorInterface $numberRangeValueGenerator;

    public function __construct(
        EntityManager $entityManager,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
    ) {
        $this->entityManager = $entityManager;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
    }

    public function patch(Context $context): void
    {
        $this->patchOrderNumbers($context);
        $this->patchOrdersToBePickable($context);
    }

    /**
     * Updates existing orders by changing the order numbers to actual numbers from the 'order' number range.
     */
    private function patchOrderNumbers(Context $context): void
    {
        // Sort by order date so the "oldest" order will have the "lowest" order number.
        $criteria = (new Criteria())
            ->addSorting(new FieldSorting('orderDateTime', FieldSorting::ASCENDING));
        $orderIds = $this->entityManager->findIdsBy(OrderDefinition::class, $criteria, $context);

        $payloads = [];
        foreach ($orderIds as $orderId) {
            $orderNumber = $this->numberRangeValueGenerator->getValue('order', $context, null);
            $payloads[] = [
                'id' => $orderId,
                'orderNumber' => $orderNumber,
            ];

            if (count($payloads) >= 50) {
                $this->entityManager->update(OrderDefinition::class, $payloads, $context);
                $payloads = [];
            }
        }
        $this->entityManager->update(OrderDefinition::class, $payloads, $context);
    }

    /**
     * Updates a fraction of the existing orders to be pickable by Pickware WMS. To be pickable we update the order
     * transaction state to 'paid'. (Currently regardless of any other conditions. This might change in the future.)
     *
     * We purposely skip selecting 'the primary order transaction' because the demo data generates single order
     * transactions per order.
     * We purposely ignore using the state machine service to create a real state transition and simplify _set_ the
     * desired state on the entity.
     */
    private function patchOrdersToBePickable(Context $context): void
    {
        /** @var StateMachineStateEntity $paidState */
        $paidState = $this->entityManager->getOneBy(
            StateMachineStateDefinition::class,
            (new Criteria())
                ->addFilter(new EqualsFilter('technicalName', OrderTransactionStates::STATE_PAID))
                ->addFilter(new EqualsFilter('stateMachine.technicalName', OrderTransactionStates::STATE_MACHINE)),
            $context,
        );

        // By only updating the 'first' n order transactions, we only update a fraction of all order transactions. Since
        // fetching without a sort parameters sorts by ID, this fraction is de facto selected randomly.
        $orderTransactionIds = $this->entityManager->findIdsBy(OrderTransactionDefinition::class, [], $context);
        $orderTransactionIds = array_slice($orderTransactionIds, 0, (int) round(count($orderTransactionIds) / 3));

        $payloads = [];
        foreach ($orderTransactionIds as $orderTransactionId) {
            $payloads[] = [
                'id' => $orderTransactionId,
                'stateId' => $paidState->getId(),
            ];

            if (count($payloads) >= 50) {
                $this->entityManager->update(OrderTransactionDefinition::class, $payloads, $context);
                $payloads = [];
            }
        }
        $this->entityManager->update(OrderTransactionDefinition::class, $payloads, $context);
    }
}
