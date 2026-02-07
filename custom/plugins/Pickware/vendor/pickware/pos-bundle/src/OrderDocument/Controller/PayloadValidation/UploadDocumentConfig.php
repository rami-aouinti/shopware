<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\OrderDocument\Controller\PayloadValidation;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Validator\Constraints\Uuid;

#[Exclude]
class UploadDocumentConfig
{
    public function __construct(
        #[Uuid(strict: false)]
        public readonly string $documentIdentifier,
        #[Uuid(strict: false)]
        public readonly ?string $orderNumber = null,
    ) {}

    public function toArray(): array
    {
        return [
            'documentIdentifier' => $this->documentIdentifier,
            'orderNumber' => $this->orderNumber ?? '',
        ];
    }
}
