<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess;

use Pickware\DalBundle\EntityManager;
use Pickware\InstallationLibrary\StateMachine\StateMachineState;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessEntity;
use Pickware\PickwareWms\Statistic\Model\PickingProcessLifecycleEventType;
use Pickware\PickwareWms\Statistic\Service\PickingProcessLifecycleEventService;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\StateTransitionService;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Transition;

class PickingProcessStateTransitionService
{
    public function __construct(
        private readonly StateTransitionService $stateTransitionService,
        private readonly PickingProcessLifecycleEventService $pickingProcessLifecycleEventService,
        private readonly EntityManager $entityManager,
    ) {}

    public function tryPickingProcessStateTransition(
        string $pickingProcessId,
        string $transitionName,
        Context $context,
        ?string $pickingProfileId = null,
    ): void {
        try {
            $this->stateTransitionService->executeStateTransition(
                new Transition(
                    PickingProcessDefinition::ENTITY_NAME,
                    $pickingProcessId,
                    $transitionName,
                    'stateId',
                ),
                $context,
            );

            $this->pickingProcessLifecycleEventService->writePickingProcessLifecycleEvent(
                PickingProcessLifecycleEventType::fromPickingProcessStateTransition($transitionName),
                $pickingProcessId,
                $pickingProfileId,
                $context,
            );
        } catch (IllegalTransitionException $e) {
            $expectedStates = (new PickingProcessStateMachine())
                ->getStatesThatAllowTransitionWithName($transitionName);

            /** @var PickingProcessEntity $pickingProcess */
            $pickingProcess = $this->entityManager->getByPrimaryKey(
                PickingProcessDefinition::class,
                $pickingProcessId,
                $context,
                ['state'],
            );

            throw PickingProcessException::invalidPickingProcessStateForAction(
                $pickingProcessId,
                $pickingProcess->getState()->getTechnicalName(),
                array_map(fn(StateMachineState $state) => $state->getTechnicalName(), $expectedStates),
            );
        }
    }
}
