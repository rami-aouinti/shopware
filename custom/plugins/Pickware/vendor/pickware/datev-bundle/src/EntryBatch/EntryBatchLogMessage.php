<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\EntryBatch;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class EntryBatchLogMessage
{
    public function __construct(
        private readonly array $content,
        private readonly array $meta,
        private readonly string $logLevel,
    ) {}

    public function getContent(): array
    {
        return $this->content;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getLogLevel(): string
    {
        return $this->logLevel;
    }

    public function toImportExportLogEntryPayload(string $importExportId): array
    {
        return [
            'importExportId' => $importExportId,
            'message' => [
                'content' => $this->content,
                'meta' => $this->meta,
            ],
            'logLevel' => $this->logLevel,
        ];
    }
}
