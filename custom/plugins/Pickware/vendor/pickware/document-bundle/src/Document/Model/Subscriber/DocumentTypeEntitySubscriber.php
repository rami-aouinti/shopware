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

use Pickware\DocumentBundle\Document\Model\DocumentTypeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DocumentTypeEntitySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            DocumentTypeDefinition::ENTITY_LOADED_EVENT => 'onDocumentTypeLoaded',
            DocumentTypeDefinition::ENTITY_PARTIAL_LOADED_EVENT => 'onDocumentTypeLoaded',
        ];
    }

    public function onDocumentTypeLoaded(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $documentType) {
            $documentType->assign([
                'description' => $documentType->get('singularDescription')->getGerman(),
            ]);
        }
    }
}
