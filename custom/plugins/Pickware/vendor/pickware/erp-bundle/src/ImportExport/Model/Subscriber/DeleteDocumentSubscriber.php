<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\Model\Subscriber;

use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityPreWriteValidationEventDispatcher;
use Pickware\DocumentBundle\Document\Model\DocumentDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DeleteDocumentSubscriber implements EventSubscriberInterface
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportDefinition::EVENT_DELETED => 'onImportExportDeleted',
            EntityPreWriteValidationEventDispatcher::getEventName(ImportExportDefinition::ENTITY_NAME) => 'onPreWriteValidationEvent',
        ];
    }

    public function onPreWriteValidationEvent($event): void
    {
        if (!($event instanceof EntityPreWriteValidationEvent)) {
            // The subscriber is probably instantiated in its old version (with the Shopware PreWriteValidationEvent) in
            // the container and will be updated on the next container rebuild (next request). Early return.
            return;
        }

        foreach ($event->getCommands() as $command) {
            if ($command instanceof DeleteCommand) {
                $command->requestChangeSet();
            }
        }
    }

    public function onImportExportDeleted(EntityDeletedEvent $event): void
    {
        $documentIdsToRemove = [];
        foreach ($event->getWriteResults() as $writeResult) {
            $changeSet = $writeResult->getChangeSet();
            if ($changeSet->getBefore('document_id') !== null) {
                $documentIdsToRemove[] = bin2hex($changeSet->getBefore('document_id'));
            }
        }
        $this->entityManager->delete(DocumentDefinition::class, $documentIdsToRemove, $event->getContext());
    }
}
