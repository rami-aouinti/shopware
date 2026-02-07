<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\CustomField\Subscriber;

use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityWriteValidationEventType;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\CustomField\PickwareCustomFieldSetModificationException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PickwareCustomFieldSetModificationValidator implements EventSubscriberInterface
{
    private const PICKWARE_NAME_PREFIX = 'pickware';

    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteValidationEventType::Pre->getEventName(CustomFieldSetDefinition::ENTITY_NAME) => 'validateModificationOfPickwareCustomFieldSets',
        ];
    }

    public function validateModificationOfPickwareCustomFieldSets(EntityPreWriteValidationEvent $event): void
    {
        if ($event->getContext()->getScope() !== Context::CRUD_API_SCOPE) {
            return;
        }

        $this->validateDeletion($event);
        $this->validateUpdate($event);
    }

    private function validateDeletion(EntityPreWriteValidationEvent $event): void
    {
        $customFieldSetIdsToValidate = ImmutableCollection::fromArray($event->getCommands())
            ->filter(fn(WriteCommand $command) => $command instanceof DeleteCommand)
            ->filter(fn(DeleteCommand $command) => isset($command->getPrimaryKey()['id']))
            ->map(fn(DeleteCommand $command) => bin2hex($command->getPrimaryKey()['id']))
            ->deduplicate();
        if ($customFieldSetIdsToValidate->isEmpty()) {
            return;
        }

        /** @var CustomFieldSetCollection $customFieldSets */
        $customFieldSets = $this->entityManager->findBy(
            CustomFieldSetDefinition::class,
            ['id' => $customFieldSetIdsToValidate->asArray()],
            $event->getContext(),
        );

        $affectedNames = ImmutableCollection::create($customFieldSets)
            ->filter(fn(CustomFieldSetEntity $customFieldSet) => $this->isPickwareCustomFieldSet($customFieldSet->getName()))
            ->map(fn(CustomFieldSetEntity $customFieldSet) => $customFieldSet->getName())
            ->deduplicate();
        if ($affectedNames->isEmpty()) {
            return;
        }

        throw PickwareCustomFieldSetModificationException::pickwareCustomFieldSetsCannotBeDeleted($affectedNames->asArray());
    }

    private function validateUpdate(EntityPreWriteValidationEvent $event): void
    {
        $customFieldSetIdsToValidate = ImmutableCollection::fromArray($event->getCommands())
            ->filter(fn(WriteCommand $command) => $command instanceof UpdateCommand)
            ->filter(fn(UpdateCommand $command) => isset($command->getPrimaryKey()['id']))
            ->map(fn(UpdateCommand $command) => bin2hex($command->getPrimaryKey()['id']))
            ->deduplicate();
        if ($customFieldSetIdsToValidate->isEmpty()) {
            return;
        }

        /** @var CustomFieldSetCollection $customFieldSets */
        $customFieldSets = $this->entityManager->findBy(
            CustomFieldSetDefinition::class,
            ['id' => $customFieldSetIdsToValidate->asArray()],
            $event->getContext(),
        );

        $affectedNames = ImmutableCollection::create($customFieldSets)
            ->filter(fn(CustomFieldSetEntity $customFieldSet) => $this->isPickwareCustomFieldSet($customFieldSet->getName()))
            ->map(fn(CustomFieldSetEntity $customFieldSet) => $customFieldSet->getName())
            ->deduplicate();
        if ($affectedNames->isEmpty()) {
            return;
        }

        throw PickwareCustomFieldSetModificationException::pickwareCustomFieldSetsCannotBeUpdated($affectedNames->asArray());
    }

    private function isPickwareCustomFieldSet(string $name): bool
    {
        return str_starts_with(mb_strtolower($name), self::PICKWARE_NAME_PREFIX);
    }
}
