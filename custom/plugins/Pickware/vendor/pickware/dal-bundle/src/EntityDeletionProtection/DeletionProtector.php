<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle\EntityDeletionProtection;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('pickware-dal-bundle.entity_deletion_protection')]
interface DeletionProtector
{
    public function getEntityDefinitionEntityName(): string;

    public function isDeletionProtected(DeleteCommand $command, Context $context): bool;

    public function getException(DeleteCommand $command, Context $context): WriteConstraintViolationException;
}
