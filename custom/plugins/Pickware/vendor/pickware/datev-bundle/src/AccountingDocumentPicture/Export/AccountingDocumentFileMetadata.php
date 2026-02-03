<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocumentPicture\Export;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AccountingDocumentFileMetadata
{
    public function __construct(
        private readonly string $documentId,
        private readonly string $documentFileName,
        private readonly string $documentFileExtension,
        private readonly string $deepLinkCode,
        private readonly string $documentPath,
    ) {}

    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    public function getDocumentFileName(): string
    {
        return $this->documentFileName;
    }

    public function getDocumentFileExtension(): string
    {
        return $this->documentFileExtension;
    }

    public function getDeepLinkCode(): string
    {
        return $this->deepLinkCode;
    }

    public function getDocumentPath(): string
    {
        return $this->documentPath;
    }
}
