<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Product;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class MainProductVariantCount extends Struct
{
    private string $mainProductId;
    private int $variantCount;

    public function __construct(string $mainProductId, int $variantCount)
    {
        $this->mainProductId = $mainProductId;
        $this->variantCount = $variantCount;
    }

    public function getMainProductId(): string
    {
        return $this->mainProductId;
    }

    public function getVariantCount(): int
    {
        return $this->variantCount;
    }
}
