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

class WarehouseStockUpdatedEvent
{
    public const EVENT_NAME = 'pickware_erp.stock.warehouse_stock_updated';

    /**
     * @var String[]
     */
    private array $warehouseIds;

    /**
     * @var String[]
     */
    private array $productIds;

    private Context $context;

    public function __construct(array $warehouseIds, array $productIds, Context $context)
    {
        $this->warehouseIds = $warehouseIds;
        $this->productIds = $productIds;
        $this->context = $context;
    }

    /**
     * @return String[]
     */
    public function getWarehouseIds(): array
    {
        return $this->warehouseIds;
    }

    /**
     * @return String[]
     */
    public function getProductIds(): array
    {
        return $this->productIds;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
