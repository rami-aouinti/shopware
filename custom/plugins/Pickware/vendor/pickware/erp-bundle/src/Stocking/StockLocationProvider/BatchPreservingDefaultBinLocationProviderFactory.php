<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocking\StockLocationProvider;

use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\ProductWarehouseConfigurationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

class BatchPreservingDefaultBinLocationProviderFactory extends AbstractBatchPreservingBinLocationProviderFactory
{
    public function createBinLocationCriteria(ProductQuantityImmutableCollection $productQuantities): Criteria
    {
        return (new Criteria())
            ->addFilter(new EqualsAnyFilter('productWarehouseConfigurations.productId', $productQuantities->getProductIds()->asArray()))
            ->addAssociation('productWarehouseConfigurations');
    }

    public function checkBinLocationCanStockProduct(BinLocationEntity $binLocation, string $productId): bool
    {
        return $binLocation
            ->getProductWarehouseConfigurations()
            ->filter(fn(ProductWarehouseConfigurationEntity $config) => $config->getProductId() === $productId)
            ->count() > 0;
    }
}
