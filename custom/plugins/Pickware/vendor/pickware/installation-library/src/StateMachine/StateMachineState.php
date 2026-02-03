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

class StateMachineState
{
    private string $technicalName;
    private array $nameTranslations;

    /**
     * List of possible state transitions:
     *    transition name (string) => target state (StateMachineState)
     *
     * @var StateMachineState[]
     */
    private array $transitions = [];

    public function __construct(string $technicalName, array $nameTranslations)
    {
        $this->technicalName = $technicalName;
        $this->nameTranslations = $nameTranslations;
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
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    public function addTransitionToState(StateMachineState $toState, string $transitionName): void
    {
        if (isset($this->transitions[$transitionName])) {
            throw new InvalidArgumentException(sprintf(
                'A transition with name "%s" already exists for state "%s".',
                $transitionName,
                $this->getTechnicalName(),
            ));
        }
        $this->transitions[$transitionName] = $toState;
    }
}
