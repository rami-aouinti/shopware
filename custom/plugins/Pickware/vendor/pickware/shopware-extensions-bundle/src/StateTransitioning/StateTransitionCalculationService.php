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
use Pickware\ShopwareExtensionsBundle\StateTransitioning\ShortestPathCalculation\ShortestPathCalculator;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\ShortestPathCalculation\WeightedEdge;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;

class StateTransitionCalculationService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ShortestPathCalculator $shortestPathCalculator,
    ) {}

    /**
     * @return array<string>|null an array of action names that need to be executed to transition from the given
     *    fromState to the given toState. Returns null if no transition is possible.
     */
    public function getFewestTransitionActionsFromStateToState(
        string $fromStateId,
        string $toStateTechnicalName,
        Context $context,
    ): ?array {
        /** @var StateMachineTransitionCollection $transitions */
        $transitions = $this->entityManager->findBy(
            StateMachineTransitionDefinition::class,
            ['stateMachine.states.id' => $fromStateId],
            $context,
        );

        if (empty($transitions->getElements())) {
            // No transitions available, no path possible.
            return null;
        }

        /** @var StateMachineStateEntity $destinationState */
        $destinationState = $this->entityManager->getOneBy(
            StateMachineStateDefinition::class,
            [
                'technicalName' => $toStateTechnicalName,
                'stateMachineId' => $transitions->first()->getStateMachineId(),
            ],
            $context,
        );

        if ($destinationState->getId() === $fromStateId) {
            // Start state is destination state, no actions needed.
            return [];
        }

        $edges = array_map(
            fn(StateMachineTransitionEntity $transition) => new WeightedEdge(
                $transition->getActionName(),
                $transition->getFromStateId(),
                $transition->getToStateId(),
                1,
            ),
            $transitions->getElements(),
        );

        $path = $this->shortestPathCalculator->calculateShortestPath(
            $edges,
            $fromStateId,
            $destinationState->getId(),
        );

        return $path ? array_map(fn(WeightedEdge $edge) => $edge->id, $path) : null;
    }
}
