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

use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IsDownloadReadyFieldCalculationSubscriber implements EventSubscriberInterface
{
    private const ALLOW_DOWNLOAD_STATES = [
        ImportExportDefinition::STATE_COMPLETED,
        ImportExportDefinition::STATE_COMPLETED_WITH_ERRORS,
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportDefinition::ENTITY_LOADED_EVENT => 'onImportExportLoaded',
            ImportExportDefinition::ENTITY_PARTIAL_LOADED_EVENT => 'onImportExportLoaded',
        ];
    }

    public function onImportExportLoaded(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $importExport) {
            $importExport->assign([
                'isDownloadReady' => false,
            ]);

            // We handle both full and partial entity loading, so we can't assume this is a complete ImportExportEntity
            // object. Since it might be a partial entity, we must use the get() method instead of the entity's getter
            // methods to access properties safely.
            if (!$importExport->get('documentId')) {
                continue;
            }

            if (
                $importExport->get('type') === ImportExportDefinition::TYPE_IMPORT || (
                    $importExport->get('type') === ImportExportDefinition::TYPE_EXPORT
                    && in_array($importExport->get('state'), self::ALLOW_DOWNLOAD_STATES)
                )
            ) {
                $importExport->assign([
                    'isDownloadReady' => true,
                ]);
            }
        }
    }
}
