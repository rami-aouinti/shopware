<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery;

use Pickware\DalBundle\EntityManager;
use Pickware\InstallationLibrary\StateMachine\StateMachineState;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Pickware\PickwareWms\PickingProcess\DeliveryStateMachine;
use Pickware\PickwareWms\PickingProcess\PickingProcessException;
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventType;
use Pickware\PickwareWms\Statistic\Service\DeliveryLifecycleEventService;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\StateTransitionService;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Transition;

class DeliveryStateTransitionService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly StateTransitionService $stateTransitionService,
        private readonly DeliveryLifecycleEventService $deliveryLifecycleEventService,
    ) {}

    public function tryDeliveryStateTransition(
        string $deliveryId,
        string $transitionName,
        Context $context,
    ): void {
        try {
            $this->stateTransitionService->executeStateTransition(
                new Transition(
                    DeliveryDefinition::ENTITY_NAME,
                    $deliveryId,
                    $transitionName,
                    'stateId',
                ),
                $context,
            );

            $this->deliveryLifecycleEventService->writeDeliveryLifecycleEvents(
                DeliveryLifecycleEventType::fromDeliveryStateTransition($transitionName),
                [$deliveryId],
                $context,
            );
        } catch (IllegalTransitionException $e) {
            $expectedStates = (new DeliveryStateMachine())
                ->getStatesThatAllowTransitionWithName($transitionName);

            /** @var DeliveryEntity $delivery */
            $delivery = $this->entityManager->getByPrimaryKey(
                DeliveryDefinition::class,
                $deliveryId,
                $context,
                ['state'],
            );

            throw PickingProcessException::invalidDeliveryStateForAction(
                $delivery->getId(),
                $delivery->getState()->getTechnicalName(),
                array_map(fn(StateMachineState $state) => $state->getTechnicalName(), $expectedStates),
            );
        }
    }
}
