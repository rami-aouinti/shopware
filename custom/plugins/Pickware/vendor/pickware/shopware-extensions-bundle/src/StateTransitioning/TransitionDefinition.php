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

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Defines the transition required to reach the target state
 * @template-covariant ReferencedEntity of Entity
 */
#[Exclude]
class TransitionDefinition
{
    /**
     * @param EntityStateDefinition<ReferencedEntity> $entityStateDefinition
     */
    public function __construct(
        private readonly EntityStateDefinition $entityStateDefinition,
        private readonly string $technicalName,
        private readonly string $currentStateId,
        private readonly ?string $targetStateId = null,
    ) {}

    /**
     * @return EntityStateDefinition<ReferencedEntity>
     */
    public function getEntityStateDefinition(): EntityStateDefinition
    {
        return $this->entityStateDefinition;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getCurrentStateId(): string
    {
        return $this->currentStateId;
    }

    public function getTargetStateId(): ?string
    {
        return $this->targetStateId;
    }
}
