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
use Pickware\PickwareErpStarter\CustomField\PickwareCustomFieldModificationException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PickwareCustomFieldModificationValidator implements EventSubscriberInterface
{
    private const PICKWARE_NAME_PREFIX = 'pickware';

    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteValidationEventType::Pre->getEventName(CustomFieldDefinition::ENTITY_NAME) => 'validateModificationOfPickwareCustomFields',
        ];
    }

    public function validateModificationOfPickwareCustomFields(EntityPreWriteValidationEvent $event): void
    {
        if ($event->getContext()->getScope() !== Context::CRUD_API_SCOPE) {
            return;
        }

        $this->validateDeletion($event);
        $this->validateUpdate($event);
        $this->validateCreation($event);
    }

    private function validateDeletion(EntityPreWriteValidationEvent $event): void
    {
        $customFieldIdsToValidate = ImmutableCollection::fromArray($event->getCommands())
            ->filter(fn(WriteCommand $command) => $command instanceof DeleteCommand)
            ->filter(fn(DeleteCommand $command) => isset($command->getPrimaryKey()['id']))
            ->map(fn(DeleteCommand $command) => bin2hex($command->getPrimaryKey()['id']))
            ->deduplicate();
        if ($customFieldIdsToValidate->isEmpty()) {
            return;
        }

        /** @var CustomFieldCollection $customFields */
        $customFields = $this->entityManager->findBy(
            CustomFieldDefinition::class,
            ['id' => $customFieldIdsToValidate->asArray()],
            $event->getContext(),
        );

        $affectedNames = ImmutableCollection::create($customFields)
            ->filter(fn(CustomFieldEntity $customField) => $this->startsWithPickwarePrefix($customField->getName()))
            ->map(fn(CustomFieldEntity $customField) => $customField->getName())
            ->deduplicate();
        if ($affectedNames->isEmpty()) {
            return;
        }

        throw PickwareCustomFieldModificationException::pickwareCustomFieldsCannotBeDeleted($affectedNames->asArray());
    }

    private function validateUpdate(EntityPreWriteValidationEvent $event): void
    {
        $customFieldIdsToValidate = ImmutableCollection::fromArray($event->getCommands())
            ->filter(fn(WriteCommand $command) => $command instanceof UpdateCommand)
            ->filter(fn(UpdateCommand $command) => isset($command->getPrimaryKey()['id']))
            ->map(fn(UpdateCommand $command) => bin2hex($command->getPrimaryKey()['id']))
            ->deduplicate();
        if ($customFieldIdsToValidate->isEmpty()) {
            return;
        }

        /** @var CustomFieldCollection $customFields */
        $customFields = $this->entityManager->findBy(
            CustomFieldDefinition::class,
            ['id' => $customFieldIdsToValidate->asArray()],
            $event->getContext(),
        );

        $affectedNames = ImmutableCollection::create($customFields)
            ->filter(fn(CustomFieldEntity $customField) => $this->startsWithPickwarePrefix($customField->getName()))
            ->map(fn(CustomFieldEntity $customField) => $customField->getName())
            ->deduplicate();
        if ($affectedNames->isEmpty()) {
            return;
        }

        throw PickwareCustomFieldModificationException::pickwareCustomFieldsCannotBeUpdated($affectedNames->asArray());
    }

    private function validateCreation(EntityPreWriteValidationEvent $event): void
    {
        $customFieldSetIdsToValidate = ImmutableCollection::fromArray($event->getCommands())
            ->filter(fn(WriteCommand $command) => $command instanceof InsertCommand)
            ->filter(fn(InsertCommand $command) => isset($command->getPayload()['set_id']))
            ->map(fn(InsertCommand $command) => bin2hex($command->getPayload()['set_id']))
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
            ->filter(fn(CustomFieldSetEntity $customFieldSet) => $this->startsWithPickwarePrefix($customFieldSet->getName()))
            ->map(fn(CustomFieldSetEntity $customFieldSet) => $customFieldSet->getName())
            ->deduplicate();
        if ($affectedNames->isEmpty()) {
            return;
        }

        throw PickwareCustomFieldModificationException::customFieldsCannotBeCreatedInPickwareCustomFieldSets($affectedNames->asArray());
    }

    private function startsWithPickwarePrefix(string $name): bool
    {
        return str_starts_with(mb_strtolower($name), self::PICKWARE_NAME_PREFIX);
    }
}
