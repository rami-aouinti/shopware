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

use Pickware\DalBundle\EntityCollectionExtension;
use Pickware\DalBundle\EntityManager;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

class StateTransitionBatchService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly BatchedStateTransitionExecutor $batchedStateTransitionExecutor,
        private readonly StateTransitionCalculationService $stateTransitionCalculationService,
    ) {}

    /**
     * Ensures a state for multiple entities at once.
     *
     * If necessary, state transitions are executed to reach the target state. The necessary transitions are calculated
     * automatically. If an entity state is already in the target state, no transition is executed.
     *
     * Each event that would normally be triggered by the Shopware service is triggered asynchronously over the message
     * queue.
     *
     * @param EntityStateDefinition<Entity> $entityStateDefinition
     * @param string[] $entityIds
     */
    public function ensureTargetStateForEntities(
        EntityStateDefinition $entityStateDefinition,
        array $entityIds,
        string $targetStateTechnicalName,
        Context $context,
    ): void {
        $entities = $this->entityManager->findBy(
            $entityStateDefinition->getEntityDefinitionClassName(),
            ['id' => $entityIds],
            $context,
        );

        $fromStateIds = array_values(array_unique(
            EntityCollectionExtension::getField($entities, $entityStateDefinition->getStateIdFieldName()),
        ));

        /** @var StateMachineStateCollection $fromStates */
        $fromStates = $this->entityManager->findBy(
            StateMachineStateDefinition::class,
            ['id' => $fromStateIds],
            $context,
        );

        foreach ($fromStates as $fromState) {
            $entitiesInState = $entities->filter(
                fn(Entity $entity) => $entity->get($entityStateDefinition->getStateIdFieldName()) === $fromState->getId(),
            );

            $actions = $this->stateTransitionCalculationService->getFewestTransitionActionsFromStateToState(
                $fromState->getId(),
                $targetStateTechnicalName,
                $context,
            );

            if ($actions === null) {
                // Should not be reached because in the default state machine that we are using (order state, order delivery
                // state, order transaction state) all transitions are possible (at least transitively)
                throw StateTransitionException::noTransitionPathToDestinationStateFound(
                    $fromState->getTechnicalName(),
                    $targetStateTechnicalName,
                    $entityStateDefinition->getEntityDefinitionClassName(),
                    $entitiesInState->first()->getId(),
                );
            }

            $fromStateId = $fromState->getId();
            foreach ($actions as $action) {
                $fromStateId = $this->batchedStateTransitionExecutor->executeStateTransitionForEntities(
                    new TransitionDefinition(
                        $entityStateDefinition,
                        $action,
                        $fromStateId,
                    ),
                    EntityCollectionExtension::getField($entitiesInState, 'id'),
                    $context,
                );
            }
        }
    }
}
