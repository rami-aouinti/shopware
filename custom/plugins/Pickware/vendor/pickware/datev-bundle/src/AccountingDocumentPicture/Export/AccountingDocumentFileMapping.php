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
class AccountingDocumentFileMapping
{
    public function __construct(
        private readonly string $documentId,
        private readonly string $accountingDocumentGuid,
        private readonly string $documentFileName,
        private readonly string $documentFileExtension,
    ) {}

    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    public function getAccountingDocumentGuid(): string
    {
        return $this->accountingDocumentGuid;
    }

    public function getDocumentFileName(): string
    {
        return $this->documentFileName;
    }

    public function getDocumentFileExtension(): string
    {
        return $this->documentFileExtension;
    }
}
