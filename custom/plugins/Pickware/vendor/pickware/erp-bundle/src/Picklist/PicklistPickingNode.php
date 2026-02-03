<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picklist;

use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PicklistPickingNode
{
    private ProductEntity $product;

    /**
     * Priority-sorted list of stock locations
     *
     * @var StockEntity[]
     */
    private array $stocks;

    private int $quantity;

    public function __construct(ProductEntity $product, array $stocks, int $quantity)
    {
        $this->product = $product;
        $this->stocks = $stocks;
        $this->quantity = $quantity;
    }

    public function getProduct(): ProductEntity
    {
        return $this->product;
    }

    /**
     * @return StockEntity[]
     */
    public function getStocks(): array
    {
        return $this->stocks;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
