<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockReservation;

use LogicException;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\Picking\PickableStockProvider;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MemoryReservedStockExcludingPickableStockProvider implements PickableStockProvider
{
    private ?ProductQuantityLocationImmutableCollection $reservedStock = null;

    public function __construct(
        #[Autowire(service: ReservedStockExcludingPickableStockProvider::class)]
        private readonly PickableStockProvider $wmsStockProvider,
    ) {}

    public function getPickableStocks(
        array $productIds,
        ?array $warehouseIds,
        Context $context,
    ): ProductQuantityLocationImmutableCollection {
        // Every PickableStockProvider returns a `ProductQuantityLocationImmutableCollection` it's just the interface
        // that hasn't been updated because of compatibility reasons.
        /** @var ProductQuantityLocationImmutableCollection $wmsPickableStock */
        $wmsPickableStock = $this->wmsStockProvider->getPickableStocks(
            $productIds,
            $warehouseIds,
            $context,
        );

        if ($this->reservedStock === null) {
            return $wmsPickableStock;
        }

        return ProductQuantityLocationImmutableCollectionExtension
            ::subtract($wmsPickableStock, $this->reservedStock)
            ->filter(fn(ProductQuantityLocation $productQuantityLocation) => $productQuantityLocation->getQuantity() > 0);
    }

    public function addToReservedStock(ProductQuantityLocationImmutableCollection $reservedStockAddition): void
    {
        if ($this->reservedStock === null) {
            throw new LogicException(
                'This method can only be called in a callback passed to method keepReservedStockStateInCallback',
            );
        }
        $this->reservedStock = ProductQuantityLocationImmutableCollectionExtension::add(
            $this->reservedStock,
            $reservedStockAddition,
        );
    }

    /**
     * This method will keep the reserved stock added with `addToReservedStock` in memory as long as the callback
     * is executed.
     *
     * Get pickable stock will now return the difference of the available stock from the parent service and the reserved
     * stock.
     *
     * @template ReturnValue
     * @param callable():ReturnValue $callback
     * @return ReturnValue
     */
    public function keepReservedStocksInCallback(callable $callback): mixed
    {
        $this->reservedStock = new ProductQuantityLocationImmutableCollection();

        try {
            return $callback();
        } finally {
            $this->reservedStock = null;
        }
    }
}
