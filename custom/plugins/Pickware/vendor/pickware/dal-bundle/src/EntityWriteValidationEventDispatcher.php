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

use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Shopware dispatches only one "pre_write_validation"/"post_write_validation" event for each call to the DAL
 * (like entity create, update or delete). The same event is dispatched for every entity that is written at once,
 * so subscribers listening on this event then need to be instantiated in the container even if they do not need
 * information about the entity in question. Only after their instantiation they can decide whether to act or not.
 *
 * This dispatcher and the new event being only scoped on individual entities solves this problem by allowing subscribers
 * to specify the concerned entities before their instantiation. This means that the symfony DI container will not
 * instantiate a subscriber if the subscriber will not act on the event.
 *
 * During plugin updates, the "plugin" entity is updated and thus a "pre_write_validation" and "post_write_validation"
 * event is dispatched. When listening on the old event, subscribers were instantiated even if they did not act on
 * the "plugin" entity. As during a plugin update the code is updated before the container refreshes, the container
 * might try to instantiate a subscriber with invalid constructor parameters. This leads to a (from the admin
 * irrecoverable) container crash. With the new event, the subscriber is not instantiated and thus the container
 * does not crash.
 *
 * This also prevents subscribers from requesting change-sets for every entity and uncovers missing change-set requests.
 *
 * See:
 * - https://github.com/pickware/shopware-plugins/issues/3500
 * - https://github.com/pickware/shopware-plugins/issues/2764
 */
class EntityWriteValidationEventDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {}

    public static function getSubscribedEvents(): array
    {
        $events = [];
        foreach (EntityWriteValidationEventType::cases() as $case) {
            $events[$case->getShopwareEventClass()] = 'onWriteValidation';
        }

        return $events;
    }

    /**
     * @param PreWriteValidationEvent|PostWriteValidationEvent $event
     */
    public function onWriteValidation(Event $event): void
    {
        $entityWriteValidationEventType = EntityWriteValidationEventType::fromEvent($event);
        $writeCommandsByEntityNames = [];
        $definitionClassNamesByEntityNames = [];
        foreach ($event->getCommands() as $command) {
            $entityName = $command->getEntityName();
            $writeCommandsByEntityNames[$entityName] ??= [];
            $writeCommandsByEntityNames[$entityName][] = $command;
            $definition = $this->definitionRegistry->getByEntityName($entityName);
            $definitionClassNamesByEntityNames[$entityName] = $definition->getClass();
        }

        foreach ($writeCommandsByEntityNames as $entityName => $writeCommands) {
            /** @var EntityPreWriteValidationEvent|EntityPostWriteValidationEvent $entityPreWriteValidationEvent */
            $entityWriteValidationEvent = $this->eventDispatcher->dispatch(
                new ($entityWriteValidationEventType->getPickwareEventClass())(
                    $event->getWriteContext(),
                    $writeCommands,
                    $definitionClassNamesByEntityNames[$entityName],
                ),
                $entityWriteValidationEventType->getEventName($entityName),
            );

            foreach ($entityWriteValidationEvent->getViolations() as $violation) {
                $event->getExceptions()->add($violation);
            }
        }
    }
}
