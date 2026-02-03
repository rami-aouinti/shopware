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

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

class StateTransitionEventDispatchingMessage implements AsyncMessageInterface
{
    /**
     * @param TransitionDefinition<Entity> $transitionDefinition
     */
    public function __construct(
        private readonly TransitionDefinition $transitionDefinition,
        private readonly string $entityId,
        private readonly Context $context,
    ) {}

    /**
     * @return TransitionDefinition<Entity>
     */
    public function getTransitionDefinition(): TransitionDefinition
    {
        return $this->transitionDefinition;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
