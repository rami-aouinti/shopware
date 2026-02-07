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

/**
 * This is a marker interface to allow some performance optimizations when executing the stocking strategy batch wise.
 *
 * It tells ERP that it can parallelize stocking of different products without changing the actual stocking result.
 *
 * A StockingStrategy request should be marked with this interface if it fulfills the following condition:
 *   The stocking result for Product A is the same no matter ...
 *     ... if the product A is stocked together with other product (that are not A) and no matter
 *     ... of the current stock distribution in any warehouse.
 *
 * Example of a stocking strategy that is product-orthogonal:
 *    "Always put a product on it's default bin location in the default warehouse."
 *    Now matter which other products get
 *    stocked together with the product, it will always result on the same warehouse. Also the current stock
 *    distribution is not relevant.
 *
 * Example of a stocking strategy that is NOT product-orthogonal:
 *    "Put the product to a bin location where the product is not stocked already."
 *    Depending on the current distribution of stock of the product in all warehouses, the target bin location could
 *    differ.
 */
interface ProductOrthogonalStockingStrategy extends StockingStrategy {}
