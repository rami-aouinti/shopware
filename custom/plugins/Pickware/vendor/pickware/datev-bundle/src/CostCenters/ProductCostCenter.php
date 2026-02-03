<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\CostCenters;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ProductCostCenter
{
    public function __construct(
        private readonly string $productNumber,
        private readonly string $costCenter,
    ) {}

    public function getProductNumber(): string
    {
        return $this->productNumber;
    }

    public function getCostCenter(): string
    {
        return $this->costCenter;
    }
}
