<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ProductSupplierConfigurationListItemReference
{
    public function __construct(
        private readonly string $productId,
        private readonly ?string $productSupplierConfigurationId,
    ) {}

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getProductSupplierConfigurationId(): ?string
    {
        return $this->productSupplierConfigurationId;
    }
}
