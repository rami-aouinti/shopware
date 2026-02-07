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
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

class BatchedStateTransitionExecutor
{
    public function __construct(
        private readonly EntityManager $entityManager,
        #[Autowire(service: 'messenger.default_bus')]
        private readonly MessageBusInterface $messageBus,
        private readonly TransitionCriteriaBuilder $transitionCriteriaBuilder,
    ) {}

    /**
     * @param TransitionDefinition<Entity> $transitionDefinition
     * @param array<string> $entityIds
     * @return string the id of the state which the entities were transitioned to
     */
    public function executeStateTransitionForEntities(
        TransitionDefinition $transitionDefinition,
        array $entityIds,
        Context $context,
    ): string {
        return $this->entityManager->runInTransactionWithRetry(function() use ($transitionDefinition, $entityIds, $context): string {
            /** @var StateMachineTransitionEntity $transition */
            $transition = $this->entityManager->findOneBy(
                StateMachineTransitionDefinition::class,
                $this->transitionCriteriaBuilder->getTransitionCriteria($transitionDefinition),
                $context,
                [
                    'fromStateMachineState',
                    'toStateMachineState',
                ],
            );

            $stateMachineHistoryPayloads = array_map(
                fn(string $entityId) => [
                    'stateMachineId' => $transition->getStateMachineId(),
                    'entityName' => $transitionDefinition->getEntityStateDefinition()->getEntityDefinitionClassName()::ENTITY_NAME,
                    'fromStateId' => $transition->getFromStateId(),
                    'toStateId' => $transition->getToStateId(),
                    'transitionActionName' => $transitionDefinition->getTechnicalName(),
                    'userId' => $context->getSource() instanceof AdminApiSource ? $context->getSource()->getUserId() : null,
                    'referencedId' => $entityId,
                    'referencedVersionId' => $context->getVersionId(),
                ],
                $entityIds,
            );

            $this->entityManager->create(
                StateMachineHistoryDefinition::class,
                $stateMachineHistoryPayloads,
                $context,
            );

            $stateChangePayloads = array_map(
                fn(string $entityId) => [
                    'id' => $entityId,
                    $transitionDefinition->getEntityStateDefinition()->getStateIdFieldName() => $transition->getToStateId(),
                ],
                $entityIds,
            );

            $this->entityManager->upsert(
                $transitionDefinition->getEntityStateDefinition()->getEntityDefinitionClassName(),
                $stateChangePayloads,
                $context,
            );

            foreach ($entityIds as $entityId) {
                $this->messageBus->dispatch(new StateTransitionEventDispatchingMessage(
                    $transitionDefinition,
                    $entityId,
                    $context,
                ));
            }

            return $transition->getToStateId();
        });
    }
}
