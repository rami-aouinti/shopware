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
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;

class OrphanedDocumentMediaFileService
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * We observed that in some cases, a media file exists for an existing document number, without referencing the
     * right document. This method deletes those orphaned media files only if they are not referenced by any
     * other document.
     *
     * Orphaned document media files are media files that:
     * - Have a fileName matching the pattern: filenamePrefix + documentNumber + filenameSuffix
     * - Are not associated with any document entity
     *
     * We need to delete them first because the fileName pattern `fileName = $fileNamePrefix . $documentNumber . $fileNameSuffix`
     * could potentially block a new file creation with the same name when regenerating a document.
     *
     * @param array<string,mixed> $documentConfig
     */
    public function deleteOrphanedDocumentMediaFiles(array $documentConfig, Context $context): void
    {
        $fileNamePrefix = $documentConfig['filenamePrefix'] ?? '';
        $fileNameSuffix = $documentConfig['filenameSuffix'] ?? '';
        $documentNumber = $documentConfig['documentNumber'] ?? '';
        $fileExtension = $documentConfig['fileTypes'] ?? [];

        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('fileName', $fileNamePrefix . $documentNumber . $fileNameSuffix),
                    new EqualsAnyFilter('fileExtension', $fileExtension),
                ],
            ),
        );

        /** @var MediaCollection $media */
        $media = $this->entityManager->findBy(
            MediaDefinition::class,
            $criteria,
            $context,
            [
                'documents',
            ],
        );

        /** @var string[] $mediaIdsToDelete */
        $mediaIdsToDelete = array_values(array_filter($media
            ->map(function(MediaEntity $media) {
                if (($media->getDocuments()?->count() ?? 0) > 0) {
                    return null;
                }

                return $media->getId();
            })));

        $this->entityManager->delete(
            MediaDefinition::class,
            $mediaIdsToDelete,
            $context,
        );
    }
}
