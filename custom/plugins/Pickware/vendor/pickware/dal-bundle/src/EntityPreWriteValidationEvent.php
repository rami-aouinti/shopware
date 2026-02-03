<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Throwable;

/**
 * Contains write commands only for a specific entity. In other ways similar to PreWriteValidationEvent. See the
 * `EntityWriteValidationEventDispatcher` for further information.
 */
class EntityPreWriteValidationEvent
{
    /**
     * @var Throwable[]
     */
    private array $violations = [];

    /**
     * @param WriteCommand[] $commands
     * @param class-string<EntityDefinition<Entity>> $definitionClassName
     */
    public function __construct(
        private readonly WriteContext $writeContext,
        private readonly array $commands,
        private readonly string $definitionClassName,
    ) {}

    public function getContext(): Context
    {
        return $this->writeContext->getContext();
    }

    public function getWriteContext(): WriteContext
    {
        return $this->writeContext;
    }

    /**
     * @return WriteCommand[]
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getDefinitionClassName(): string
    {
        return $this->definitionClassName;
    }

    /**
     * @return Throwable[]
     */
    public function getViolations(): array
    {
        return $this->violations;
    }

    public function addViolation(Throwable $violation): void
    {
        $this->violations[] = $violation;
    }
}
