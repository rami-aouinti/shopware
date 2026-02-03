<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use Shopware\Core\Framework\Context;

class StockNotAvailableForSaleUpdatedForAllProductsInWarehousesEvent
{
    /**
     * @param list<string> $warehouseIds
     * @param bool $warehouseStockIsNowNotAvailableForSale TRUE, if the warehouses changed from being online to offline:
     * the stock not available for sale is now increased for all products in these warehouses. FALSE, if the warehouses
     * changed from being offline to online: the stock not available for sale is now decreased for all products in these
     * warehouses.
     */
    public function __construct(
        private readonly array $warehouseIds,
        private readonly bool $warehouseStockIsNowNotAvailableForSale,
        private readonly Context $context,
    ) {}

    /**
     * @return list<string>
     */
    public function getWarehouseIds(): array
    {
        return $this->warehouseIds;
    }

    public function isWarehouseStockNowNotAvailableForSale(): bool
    {
        return $this->warehouseStockIsNowNotAvailableForSale;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
