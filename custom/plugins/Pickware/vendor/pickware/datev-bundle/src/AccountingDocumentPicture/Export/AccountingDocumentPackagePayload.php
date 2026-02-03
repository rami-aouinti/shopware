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

use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AccountingDocumentPackagePayload implements JsonSerializable
{
    public function __construct(
        private readonly string $documentExportId,
        private readonly string $fileName,
        private readonly int $limit,
        private readonly int $offset,
    ) {}

    public function getDocumentExportId(): string
    {
        return $this->documentExportId;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'documentExportId' => $this->documentExportId,
            'fileName' => $this->fileName,
            'limit' => $this->limit,
            'offset' => $this->offset,
        ];
    }
}
