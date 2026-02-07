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
use Symfony\Component\Validator\Constraints\NotBlank;

#[Exclude]
class PickwarePosAdditionalElement
{
    public function __construct(
        #[NotBlank]
        public readonly string $type,
        public readonly ?string $title,
        #[NotBlank]
        public readonly string $value,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'value' => $this->value,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['type'],
            $data['title'] ?? null,
            $data['value'],
        );
    }
}
