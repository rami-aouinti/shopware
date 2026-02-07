<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderPickability;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * An event dispatched by `OrderPickabilityCalculator` that allows subscribers to customize the SQL used for calculating
 * the order pickability.
 *
 * The following extensions are currently supported:
 *   - `$reservedWarehouseStockQuery`: Use this query to join another table (or sub-query) that provides reserved
 *     stock per product and warehouse. For the table/query to be joined correctly it must provide the fields `product`,
 *     `product_version_id`, `warehouse_id` and `quantity`. Please note that any complex query (i.e. not a plain table
 *     name) provided by this extension must be wrapped in parentheses.
 */
class OrderPickabilityQueryExtensionEvent extends Event
{
    private ?string $reservedWarehouseStockQuery = null;

    public function __construct() {}

    public function setReservedWarehouseStockQuery(?string $reservedWarehouseStockQuery): void
    {
        $this->reservedWarehouseStockQuery = $reservedWarehouseStockQuery;
    }

    public function getReservedWarehouseStockQuery(): ?string
    {
        return $this->reservedWarehouseStockQuery;
    }
}
