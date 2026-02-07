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
use Symfony\Component\Validator\Constraints\Valid;

#[Exclude]
class DocumentConfig
{
    public function __construct(
        #[Valid]
        /** @var PickwarePosAdditionalElement[] */
        public readonly array $pickwarePosAdditionalElements,
    ) {}

    public function toArray(): array
    {
        return [
            'pickwarePosAdditionalElements' => array_map(
                fn($pickwarePosAdditionalElement) => $pickwarePosAdditionalElement->toArray(),
                $this->pickwarePosAdditionalElements,
            ),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            array_map(
                fn($pickwarePosAdditionalElementData) => PickwarePosAdditionalElement::fromArray($pickwarePosAdditionalElementData),
                $data['pickwarePosAdditionalElements'],
            ),
        );
    }
}
