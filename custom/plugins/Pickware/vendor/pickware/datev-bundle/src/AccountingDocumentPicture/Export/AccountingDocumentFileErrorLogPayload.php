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
class AccountingDocumentFileErrorLogPayload
{
    public function __construct(
        private readonly string $orderNumber,
        private readonly string $documentNumber,
        private readonly string $documentType,
        private readonly string $accountingDocumentGuid,
        private readonly string $error,
    ) {}

    public function toLogEntry(): string
    {
        return sprintf(
            <<<LOG
                Bestellung: %s
                Dokumentnummer: %s
                Dokumenttyp: %s
                GUID: %s
                Fehler: %s
                ----------------------------------------
                LOG,
            $this->orderNumber,
            $this->documentNumber,
            $this->documentType,
            $this->accountingDocumentGuid,
            $this->error,
        );
    }
}
