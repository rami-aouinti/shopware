<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocking;

use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class StockingRequest
{
    private ProductQuantityImmutableCollection $productQuantities;
    private StockArea $stockArea;

    /**
     * @param ProductQuantity[]|ProductQuantityImmutableCollection $productQuantities
     * @param null|string|StockArea $warehouseId
     */
    public function __construct(
        mixed $productQuantities,
        mixed $warehouseId = null,
        ?StockArea $stockArea = null,
    ) {
        if (is_array($productQuantities)) {
            trigger_error(
                'Calling the initializer with an array as productQuantities is deprecated. Use a ProductQuantityImmutableCollection instead. Will crash in v5.0.0',
                E_USER_DEPRECATED,
            );
            $this->productQuantities = ProductQuantityImmutableCollection
                ::create($productQuantities)
                ->filter(fn(ProductQuantity $productQuantity) => $productQuantity->getQuantity() > 0);
            $this->stockArea = $warehouseId === null ? StockArea::everywhere() : StockArea::warehouse($warehouseId);

            return;
        }

        $this->productQuantities = $productQuantities->filter(
            fn(ProductQuantity $productQuantity) => $productQuantity->getQuantity() > 0,
        );

        if ($warehouseId instanceof StockArea) {
            $this->stockArea = $warehouseId;
        } else {
            $this->stockArea = $stockArea;
        }
    }

    public function getProductQuantities(): ProductQuantityImmutableCollection
    {
        return $this->productQuantities;
    }

    public function getStockArea(): StockArea
    {
        return $this->stockArea;
    }
}
