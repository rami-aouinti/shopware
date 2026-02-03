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
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: StateTransitionEventDispatchingMessage::class)]
class StateTransitionEventDispatchingMessageHandler
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly TransitionCriteriaBuilder $transitionCriteriaBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function __invoke(StateTransitionEventDispatchingMessage $message): void
    {
        $context = $message->getContext();

        /** @var StateMachineTransitionEntity $transition */
        $transition = $this->entityManager->getOneBy(
            StateMachineTransitionDefinition::class,
            $this->transitionCriteriaBuilder->getTransitionCriteria($message->getTransitionDefinition()),
            $context,
            [
                'stateMachine',
                'fromStateMachineState',
                'toStateMachineState',
            ],
        );

        $this->eventDispatcher->dispatch(
            new StateMachineTransitionEvent(
                $message->getTransitionDefinition()->getEntityStateDefinition()->getEntityDefinitionClassName(),
                $message->getEntityId(),
                $transition->getFromStateMachineState(),
                $transition->getToStateMachineState(),
                $context,
            ),
        );

        $leaveEvent = new StateMachineStateChangeEvent(
            $context,
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_LEAVE,
            new Transition(
                $message->getTransitionDefinition()->getEntityStateDefinition()->getEntityDefinitionClassName(),
                $message->getEntityId(),
                $message->getTransitionDefinition()->getTechnicalName(),
                $message->getTransitionDefinition()->getEntityStateDefinition()->getStateIdFieldName(),
            ),
            $transition->getStateMachine(),
            $transition->getFromStateMachineState(),
            $transition->getToStateMachineState(),
        );

        $this->eventDispatcher->dispatch(
            $leaveEvent,
            $leaveEvent->getName(),
        );

        $enterEvent = new StateMachineStateChangeEvent(
            $context,
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
            new Transition(
                $message->getTransitionDefinition()->getEntityStateDefinition()->getEntityDefinitionClassName(),
                $message->getEntityId(),
                $message->getTransitionDefinition()->getTechnicalName(),
                $message->getTransitionDefinition()->getEntityStateDefinition()->getStateIdFieldName(),
            ),
            $transition->getStateMachine(),
            $transition->getFromStateMachineState(),
            $transition->getToStateMachineState(),
        );

        $this->eventDispatcher->dispatch(
            $enterEvent,
            $enterEvent->getName(),
        );
    }
}
