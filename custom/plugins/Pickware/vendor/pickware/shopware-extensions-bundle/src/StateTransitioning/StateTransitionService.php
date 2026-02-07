<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\StateTransitioning;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class StateTransitionService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly StateTransitionCalculationService $stateTransitionCalculationService,
    ) {}

    /**
     * @param string $entityName (e.g. 'order'. NOT the entity class name)
     * @deprecated Will be removed in 3.0.0. Use 'executeStateTransition' or 'executeStateTransitionIfNotAlreadyInTargetState' instead.
     */
    public function transitionState(
        string $entityName,
        string $entityId,
        string $transitionActionName,
        string $stateIdFieldName,
        Context $context,
    ): void {
        $this->stateMachineRegistry->transition(
            new Transition($entityName, $entityId, $transitionActionName, $stateIdFieldName),
            $context,
        );
    }

    /**
     * Executes the given state transition. Throws an error when the target state is already reached.
     */
    public function executeStateTransition(
        Transition $transition,
        Context $context,
    ): void {
        $this->entityManager->runInTransactionWithRetry(function() use ($transition, $context): void {
            $stateMachineStateCollection = $this->stateMachineRegistry->transition($transition, $context);

            $fromPlace = $stateMachineStateCollection->get('fromPlace');
            $toPlace = $stateMachineStateCollection->get('toPlace');

            if (
                $toPlace !== null
                && $fromPlace !== null
                && $toPlace->getId() === $fromPlace->getId()
            ) {
                $transitions = $this->stateMachineRegistry
                    ->getAvailableTransitions(
                        $transition->getEntityName(),
                        $transition->getEntityId(),
                        $transition->getStateFieldName(),
                        $context,
                    );
                $transitionNames = array_map(
                    fn(StateMachineTransitionEntity $transition) => $transition->getActionName(),
                    $transitions,
                );

                throw new IllegalTransitionException(
                    $fromPlace->getId(),
                    $transition->getTransitionName(),
                    $transitionNames,
                );
            }
        });
    }

    /**
     * Executes the given state transition only if the entity did not reach the target state yet.
     *
     * This is shopwares default behavior. Please always try to prefer our method `executeStateTransition`.
     */
    public function executeStateTransitionIfNotAlreadyInTargetState(
        Transition $transition,
        Context $context,
    ): void {
        $this->stateMachineRegistry->transition($transition, $context);
    }

    /**
     * Ensures an order is in the desired state by creating state transitions that result in the desired state if
     * such a combination of transitions exists.
     */
    public function ensureOrderState(
        string $orderId,
        string $desiredStateTechnicalName,
        Context $context,
    ): void {
        $this->ensureState(
            $orderId,
            OrderDefinition::class,
            OrderDefinition::ENTITY_NAME,
            $desiredStateTechnicalName,
            $context,
        );
    }

    /**
     * Ensures an order delivery is in the desired state by creating state transitions that result in the desired state
     * if such a combination of transitions exists.
     */
    public function ensureOrderDeliveryState(
        string $orderDeliveryId,
        string $desiredStateTechnicalName,
        Context $context,
    ): void {
        $this->ensureState(
            $orderDeliveryId,
            OrderDeliveryDefinition::class,
            OrderDeliveryDefinition::ENTITY_NAME,
            $desiredStateTechnicalName,
            $context,
        );
    }

    /**
     * Ensures an order transaction is in the desired state by creating state transitions that result in the desired
     * state if such a combination of transitions exists.
     */
    public function ensureOrderTransactionState(
        string $orderTransactionId,
        string $desiredStateTechnicalName,
        Context $context,
    ): void {
        $this->ensureState(
            $orderTransactionId,
            OrderTransactionDefinition::class,
            OrderTransactionDefinition::ENTITY_NAME,
            $desiredStateTechnicalName,
            $context,
        );
    }

    /**
     * Helper function to reduce code duplication. Therefore, it works only for order state, order delivery state, order
     * transaction state. This is because we assume association names, getter functions and property names.
     *
     * @param class-string<EntityDefinition<Entity>> $classDefinitionName
     */
    private function ensureState(
        string $entityId,
        string $classDefinitionName,
        string $entityName,
        string $desiredStateTechnicalName,
        Context $context,
    ): void {
        $entity = $this->entityManager->getByPrimaryKey(
            $classDefinitionName,
            $entityId,
            $context,
            ['stateMachineState'],
        );

        $actions = $this->stateTransitionCalculationService->getFewestTransitionActionsFromStateToState(
            $entity->getStateMachineState()->getId(),
            $desiredStateTechnicalName,
            $context,
        );

        if ($actions === null) {
            // Should not be reached because in the default state machine that we are using (order state, order delivery
            // state, order transaction state) all transitions are possible (at least transitively)
            throw StateTransitionException::noTransitionPathToDestinationStateFound(
                $entity->getStateMachineState()->getTechnicalName(),
                $desiredStateTechnicalName,
                $classDefinitionName,
                $entityId,
            );
        }

        foreach ($actions as $action) {
            $this->stateMachineRegistry->transition(
                new Transition($entityName, $entityId, $action, 'stateId'),
                $context,
            );
        }
    }
}
