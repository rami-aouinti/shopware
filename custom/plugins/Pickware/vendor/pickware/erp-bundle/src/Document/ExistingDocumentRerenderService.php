<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Document;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;

class ExistingDocumentRerenderService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly OrphanedDocumentMediaFileService $orphanedDocumentMediaFileService,
    ) {}

    /**
     * Checks if the media file of the given operations is missing, and if so, forces the document creation and returns the updated operations.
     * @param DocumentGenerateOperation[] $operations
     * @return DocumentGenerateOperation[]
     */
    public function filterAndConfigureOperationsForMissingMedia(array $operations, Context $context): array
    {
        $operationsWithDocumentId = array_filter($operations, fn(DocumentGenerateOperation $operation) => $operation->getDocumentId() !== null);
        /** @var DocumentCollection $documents */
        $documents = $this->entityManager->findBy(
            DocumentDefinition::class,
            [
                'id' => array_values(
                    array_map(fn(DocumentGenerateOperation $operation) => $operation->getDocumentId(), $operationsWithDocumentId),
                ),
            ],
            $context,
            [
                'documentMediaFile',
            ],
        );

        foreach ($operationsWithDocumentId as $key => $operation) {
            $document = $documents->get($operation->getDocumentId());
            if ($document === null || ($document->getDocumentMediaFile() !== null && $document->getDocumentMediaFile()->hasFile())) {
                // If the media file exists, we do not need to prepare the operation and can remove it from the list.
                unset($operationsWithDocumentId[$key]);

                continue;
            }

            $operation->assign([
                'config' => [
                    ...$operation->getConfig(),
                    'forceDocumentCreation' => true, // Force document creation even if the media file is missing
                ],
            ]);
            $operation->setOrderVersionId($document->getOrderVersionId());

            $context->scope(Context::SYSTEM_SCOPE, function(Context $systemScopeContext) use ($document): void {
                $this->orphanedDocumentMediaFileService->deleteOrphanedDocumentMediaFiles($document->getConfig(), $systemScopeContext);
            });
        }

        return $operationsWithDocumentId;
    }
}
