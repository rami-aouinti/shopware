<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Document\Model\Subscriber;

use League\Flysystem\FilesystemOperator;
use Pickware\DalBundle\EntityPreWriteValidationEvent;
use Pickware\DalBundle\EntityPreWriteValidationEventDispatcher;
use Pickware\DocumentBundle\Document\Model\DocumentDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DeleteFileSubscriber implements EventSubscriberInterface
{
    private FilesystemOperator $privateFileSystem;

    public function __construct(FilesystemOperator $privateFileSystem)
    {
        $this->privateFileSystem = $privateFileSystem;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DocumentDefinition::ENTITY_DELETED_EVENT => 'onDocumentEntityDeleted',
            EntityPreWriteValidationEventDispatcher::getEventName(DocumentDefinition::ENTITY_NAME) => 'onPreWriteValidationEvent',
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

    public function onDocumentEntityDeleted(EntityDeletedEvent $event): void
    {
        foreach ($event->getWriteResults() as $writeResult) {
            $changeSet = $writeResult->getChangeSet();
            $filePath = $changeSet->getBefore('path_in_private_file_system');
            if ($filePath === null) {
                $filePath = 'documents/' . bin2hex($changeSet->getBefore('id'));
            }
            if ($this->privateFileSystem->fileExists($filePath)) {
                $this->privateFileSystem->delete($filePath);
            }
        }
    }
}
