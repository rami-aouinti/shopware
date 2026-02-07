<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\StateMachine;

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Shopware\Core\System\StateMachine\StateMachineEntity;

class StateMachineInstaller
{
    public function __construct(private readonly EntityManager $entityManager) {}

    public function ensureStateMachine(StateMachine $stateMachine, Context $context): void
    {
        /** @var StateMachineEntity|null $existingNumberRange */
        $existingStateMachine = $this->entityManager->findOneBy(
            StateMachineDefinition::class,
            ['technicalName' => $stateMachine->getTechnicalName()],
            $context,
        );
        /** @var string $stateMachineId */
        $stateMachineId = $existingStateMachine ? $existingStateMachine->getId() : Uuid::randomHex();
        $this->entityManager->upsert(
            StateMachineDefinition::class,
            [
                [
                    'id' => $stateMachineId,
                    'technicalName' => $stateMachine->getTechnicalName(),
                    'name' => $stateMachine->getNameTranslations(),
                ],
            ],
            $context,
        );

        foreach ($stateMachine->getStates() as $state) {
            $stateMachineStateId = $this->ensureStateMachineState($state, $stateMachineId, $context);

            // Also set initial state id if necessary
            if ($stateMachine->getInitialState()->getTechnicalName() === $state->getTechnicalName()) {
                $this->entityManager->upsert(
                    StateMachineDefinition::class,
                    [
                        [
                            'id' => $stateMachineId,
                            'initialStateId' => $stateMachineStateId,
                        ],
                    ],
                    $context,
                );
            }
        }

        // Install state transitions _after_ all states have been created
        $stateMachineStates = $this->entityManager->findBy(
            StateMachineStateDefinition::class,
            ['stateMachineId' => $stateMachineId],
            $context,
        )->getElements();
        $stateMachineStatesByTechnicalName = array_combine(
            array_map(fn(StateMachineStateEntity $state) => $state->getTechnicalName(), $stateMachineStates),
            $stateMachineStates,
        );

        // https://github.com/shopware/shopware/issues/11157
        // Due to a shopware 6.7.0.0 migration bug, there may be state machine state transitions with a "faulty" name in
        // the database. Therefore, we delete any transitions which action names are not used by the state machine.
        $validActionNames = $stateMachine->getActionNames();
        if (!$validActionNames->isEmpty()) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachineId));
            $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [
                new EqualsAnyFilter('actionName', $validActionNames->asArray()),
            ]));

            $this->entityManager->deleteByCriteria(
                StateMachineTransitionDefinition::class,
                $criteria,
                $context,
            );
        }

        foreach ($stateMachine->getStates() as $state) {
            foreach ($state->getTransitions() as $actionName => $targetState) {
                if (
                    !array_key_exists($state->getTechnicalName(), $stateMachineStatesByTechnicalName)
                    || !array_key_exists($targetState->getTechnicalName(), $stateMachineStatesByTechnicalName)
                ) {
                    throw new InvalidArgumentException(sprintf(
                        'Invalid state machine state transition definition. State transition from %s to %s not ' .
                        'possible because one of the state machine states does not exist.',
                        $state->getTechnicalName(),
                        $targetState->getTechnicalName(),
                    ));
                }

                $this->ensureStateMachineStateTransition(
                    $stateMachineId,
                    $stateMachineStatesByTechnicalName[$state->getTechnicalName()]->getId(),
                    $stateMachineStatesByTechnicalName[$targetState->getTechnicalName()]->getId(),
                    $actionName,
                    $context,
                );
            }
        }

        // Remove old states from DB
        /** @var StateMachineEntity $stateMachineInDb */
        $stateMachineInDb = $this->entityManager->getByPrimaryKey(
            StateMachineDefinition::class,
            $stateMachineId,
            $context,
            [
                'transitions.fromStateMachineState',
                'transitions.toStateMachineState',
                'states',
            ],
        );
        $transitionIdsToBeDeleted = [];
        /** @var StateMachineTransitionEntity $transition */
        foreach ($stateMachineInDb->getTransitions() as $transition) {
            if (
                !$stateMachine->allowsTransitionFromStateToState(
                    $transition->getFromStateMachineState()->getTechnicalName(),
                    $transition->getToStateMachineState()->getTechnicalName(),
                )
            ) {
                $transitionIdsToBeDeleted[] = $transition->getId();
            }
        }
        $this->entityManager->delete(
            StateMachineTransitionDefinition::class,
            $transitionIdsToBeDeleted,
            $context,
        );
        $stateIdsToBeDeleted = [];
        /** @var StateMachineStateEntity $state */
        foreach ($stateMachineInDb->getStates() as $state) {
            if (!$stateMachine->hasStateWithTechnicalName($state->getTechnicalName())) {
                $stateIdsToBeDeleted[] = $state->getId();
            }
        }
        // We need to remove the affected history entries by hand because the foreign key constraint is
        // ON DELETE NO ACTION, which is ON DELETE RESTRICT
        $this->entityManager->deleteByCriteria(
            StateMachineHistoryDefinition::class,
            ['fromStateId' => $stateIdsToBeDeleted],
            $context,
        );
        $this->entityManager->deleteByCriteria(
            StateMachineHistoryDefinition::class,
            ['toStateId' => $stateIdsToBeDeleted],
            $context,
        );
        $this->entityManager->delete(
            StateMachineStateDefinition::class,
            $stateIdsToBeDeleted,
            $context,
        );
    }

    public function removeStateMachine(StateMachine $stateMachine, Context $context): void
    {
        // First remove all history entries because they restrict the deletion of the states via foreign key constraint
        $this->entityManager->deleteByCriteria(
            StateMachineHistoryDefinition::class,
            ['stateMachine.technicalName' => $stateMachine->getTechnicalName()],
            $context,
        );

        // Then remove all transitions because they also restrict the deletion of the states via foreign key constraint
        $this->entityManager->deleteByCriteria(
            StateMachineTransitionDefinition::class,
            ['stateMachine.technicalName' => $stateMachine->getTechnicalName()],
            $context,
        );

        $this->entityManager->deleteByCriteria(
            StateMachineDefinition::class,
            ['technicalName' => $stateMachine->getTechnicalName()],
            $context,
        );
    }

    private function ensureStateMachineState(
        StateMachineState $state,
        string $stateMachineId,
        Context $context,
    ): string {
        $existingStateMachineState = $this->entityManager->findOneBy(
            StateMachineStateDefinition::class,
            [
                'stateMachineId' => $stateMachineId,
                'technicalName' => $state->getTechnicalName(),
            ],
            $context,
        );
        $stateMachineStateId = $existingStateMachineState ? $existingStateMachineState->getId() : Uuid::randomHex();
        $this->entityManager->upsert(
            StateMachineStateDefinition::class,
            [
                [
                    'id' => $stateMachineStateId,
                    'stateMachineId' => $stateMachineId,
                    'technicalName' => $state->getTechnicalName(),
                    'name' => $state->getNameTranslations(),
                ],
            ],
            $context,
        );

        return $stateMachineStateId;
    }

    private function ensureStateMachineStateTransition(
        string $stateMachineId,
        string $fromStateMachineStateId,
        string $toStateMachineStateId,
        string $actionName,
        Context $context,
    ): void {
        $existingTransition = $this->entityManager->findOneBy(
            StateMachineTransitionDefinition::class,
            [
                'stateMachineId' => $stateMachineId,
                'fromStateId' => $fromStateMachineStateId,
                'toStateId' => $toStateMachineStateId,
            ],
            $context,
        );
        $transitionId = $existingTransition ? $existingTransition->getId() : Uuid::randomHex();
        $this->entityManager->upsert(
            StateMachineTransitionDefinition::class,
            [
                [
                    'id' => $transitionId,
                    'stateMachineId' => $stateMachineId,
                    'fromStateId' => $fromStateMachineStateId,
                    'toStateId' => $toStateMachineStateId,
                    'actionName' => $actionName,
                ],
            ],
            $context,
        );
    }
}
