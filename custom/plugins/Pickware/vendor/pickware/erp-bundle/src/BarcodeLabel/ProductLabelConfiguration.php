<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\BarcodeLabel;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
readonly class ProductLabelConfiguration
{
    public function __construct(
        private string $productId,
        private int $barcodeLabelCount,
    ) {}

    public static function fromArray(array $array): self
    {
        if (!isset($array['productId'])) {
            throw new InvalidArgumentException('Required parameters "productId" is missing.');
        }

        return new self(
            $array['productId'],
            (int) ($array['barcodeLabelCount'] ?? 1),
        );
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getBarcodeLabelCount(): int
    {
        return $this->barcodeLabelCount;
    }
}
