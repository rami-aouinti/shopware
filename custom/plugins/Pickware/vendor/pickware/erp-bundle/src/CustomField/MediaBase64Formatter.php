<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\CustomField;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;

class MediaBase64Formatter
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly MediaService $mediaService,
    ) {}

    public function formatMedia(string $mediaId, Context $context): string
    {
        /** @var MediaEntity $media */
        $media = $this->entityManager->getByPrimaryKey(
            MediaDefinition::class,
            $mediaId,
            $context,
            ['mediaType'],
        );
        if ($media->getMediaType()->getName() !== 'IMAGE' || !$media->hasFile()) {
            throw MediaFormatterError::mediaTypeNotSupported($media->getMediaType()->getName());
        }

        $mimeType = $media->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw MediaFormatterError::mediaTypeNotSupported($mimeType);
        }

        $maxSizeInBytes = 5 * 1024 * 1024; // 5MB limit for PDF embedding
        if ($media->getFileSize() > $maxSizeInBytes) {
            throw MediaFormatterError::fileSizeTooBig($media->getFileSize(), $maxSizeInBytes);
        }

        return $this->getBase64ImageData($media, $context);
    }

    private function getBase64ImageData(MediaEntity $media, Context $context): string
    {
        $fileContent = '';
        $context->scope(Context::SYSTEM_SCOPE, function(Context $systemContext) use ($media, &$fileContent): void {
            $fileContent = $this->mediaService->loadFile($media->getId(), $systemContext);
        });

        return base64_encode($fileContent);
    }
}
