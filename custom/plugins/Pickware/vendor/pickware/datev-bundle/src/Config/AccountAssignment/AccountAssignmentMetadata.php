<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\AccountAssignment;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AccountAssignmentMetadata
{
    public function __construct(
        private readonly string $documentType,
        private readonly string $documentNumber,
        private readonly string $orderNumber,
    ) {}

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function getDocumentNumber(): string
    {
        return $this->documentNumber;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }
}
