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

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DeletionProtectorRegistry implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'onPreWriteValidation'];
    }

    public function __construct(
        /** @var iterable<DeletionProtector> $deletionProtectionConfigurations */
        #[TaggedIterator('pickware-dal-bundle.entity_deletion_protection')]
        private readonly iterable $deletionProtectionConfigurations,
    ) {}

    public function onPreWriteValidation(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (!$command instanceof DeleteCommand) {
                continue;
            }

            foreach ($this->deletionProtectionConfigurations as $deletionProtectionConfiguration) {
                if ($deletionProtectionConfiguration->getEntityDefinitionEntityName() !== $command->getEntityName()) {
                    continue;
                }

                if ($deletionProtectionConfiguration->isDeletionProtected($command, $event->getContext())) {
                    $event->getExceptions()->add($deletionProtectionConfiguration->getException($command, $event->getContext()));
                }
            }
        }
    }
}
