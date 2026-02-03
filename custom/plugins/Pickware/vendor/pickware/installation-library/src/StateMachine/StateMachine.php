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

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;

class StateMachine
{
    private string $technicalName;
    private array $nameTranslations;
    private StateMachineState $initialState;

    /**
     * @var StateMachineState[]
     */
    private array $states;

    /**
     * @param array $nameTranslations Name (property 'name') translations for the locale codes de-DE and en-GB. E.g. [
     *   'de-DE' => 'Meine Status Maschine',
     *   'en-GB' => 'My State Machine',
     * ]
     * @param StateMachineState[] $states
     */
    public function __construct(
        string $technicalName,
        array $nameTranslations,
        array $states,
        StateMachineState $initialState,
    ) {
        $this->technicalName = $technicalName;
        $this->nameTranslations = $nameTranslations;
        $this->states = $states;
        $this->initialState = $initialState;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getNameTranslations(): array
    {
        return $this->nameTranslations;
    }

    /**
     * @return StateMachineState[]
     */
    public function getStates(): array
    {
        return $this->states;
    }

    public function getInitialState(): StateMachineState
    {
        return $this->initialState;
    }

    public function addTransitionsFromAllStatesToState(StateMachineState $toState, string $transitionName): void
    {
        foreach ($this->states as $fromState) {
            if ($fromState->getTechnicalName() === $toState->getTechnicalName()) {
                continue;
            }
            $fromState->addTransitionToState($toState, $transitionName);
        }
    }

    public function allowsTransitionFromStateToState(string $fromStateTechnicalName, string $toStateTechnicalName): bool
    {
        $fromState = $this->getStateByTechnicalName($fromStateTechnicalName);
        if (!$fromState) {
            return false;
        }

        foreach ($fromState->getTransitions() as $transition) {
            if ($transition->getTechnicalName() === $toStateTechnicalName) {
                return true;
            }
        }

        return false;
    }

    private function getStateByTechnicalName(string $technicalName): ?StateMachineState
    {
        foreach ($this->states as $state) {
            if ($state->getTechnicalName() === $technicalName) {
                return $state;
            }
        }

        return null;
    }

    /**
     * @return StateMachineState[] a list of states that have a transition with the given transition name _from_ them (_to_ any other state)
     */
    public function getStatesThatAllowTransitionWithName(string $transitionName): array
    {
        $validFromStates = [];
        foreach ($this->getStates() as $state) {
            foreach (array_keys($state->getTransitions()) as $currentTransitionName) {
                if ($currentTransitionName === $transitionName) {
                    $validFromStates[] = $state;
                    break;
                }
            }
        }

        return $validFromStates;
    }

    public function hasStateWithTechnicalName(string $technicalName): bool
    {
        return $this->getStateByTechnicalName($technicalName) !== null;
    }

    /**
     * @return ImmutableCollection<string>
     */
    public function getActionNames(): ImmutableCollection
    {
        $actionNames = [];
        foreach ($this->getStates() as $state) {
            foreach (array_keys($state->getTransitions()) as $actionName) {
                $actionNames[] = $actionName;
            }
        }

        return ImmutableCollection::create(array_unique($actionNames));
    }
}
