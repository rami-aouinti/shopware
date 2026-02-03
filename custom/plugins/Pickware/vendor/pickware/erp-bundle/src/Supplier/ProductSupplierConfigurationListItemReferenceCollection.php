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

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @implements ImmutableCollection<ProductSupplierConfigurationListItemReference>
 */
#[Exclude]
class ProductSupplierConfigurationListItemReferenceCollection extends ImmutableCollection
{
    /**
     * @return string[]
     */
    public function getProductIds(): array
    {
        return array_unique($this->map(
            fn(ProductSupplierConfigurationListItemReference $productStockReference) => $productStockReference->getProductId(),
        )->asArray());
    }

    /**
     * @return string[]
     */
    public function getProductSupplierConfigurationIds(): array
    {
        return array_filter(array_unique($this->map(
            fn(ProductSupplierConfigurationListItemReference $productStockReference) => $productStockReference->getProductSupplierConfigurationId(),
        )->asArray()));
    }
}
