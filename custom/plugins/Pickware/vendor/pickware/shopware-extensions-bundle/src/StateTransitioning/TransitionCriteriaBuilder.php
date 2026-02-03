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

use InvalidArgumentException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class TransitionCriteriaBuilder
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {}

    /**
     * @param TransitionDefinition<Entity> $transitionDefinition
     */
    public function getTransitionCriteria(TransitionDefinition $transitionDefinition): Criteria
    {
        $stateIdField = $this->definitionRegistry
            ->get($transitionDefinition->getEntityStateDefinition()->getEntityDefinitionClassName())
            ->getField($transitionDefinition->getEntityStateDefinition()->getStateIdFieldName());

        if (!($stateIdField instanceof StateMachineStateField)) {
            throw new InvalidArgumentException(
                'Invalid entity definition provided when building transition criteria, state machine could not be determined',
            );
        }

        return (new Criteria())
            ->addFilter(
                new EqualsFilter('actionName', $transitionDefinition->getTechnicalName()),
                new EqualsFilter(
                    'fromStateId',
                    $transitionDefinition->getCurrentStateId(),
                ),
                new EqualsFilter(
                    'stateMachine.technicalName',
                    $stateIdField->getStateMachineName(),
                ),
            );
    }
}
