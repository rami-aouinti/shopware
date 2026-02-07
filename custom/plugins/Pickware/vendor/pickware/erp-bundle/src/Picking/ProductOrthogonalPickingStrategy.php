<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picking;

/**
 * This is a marker interface to allow some performance optimizations when executing the picking strategy batch wise.
 *
 * It tells ERP that it can parallelize picking of different products without changing the actual picking result.
 *
 * A PickingStrategy request should be marked with this interface if it fulfills the following condition:
 *   The picking result for Product A is the same no matter ...
 *     ... if the product A is picked together with other product (that are not A) and no matter
 *     ... of the current stock distribution in any warehouse.
 *
 * Example of a picking strategy that is product-orthogonal:
 *    "Always take a product from it's default bin location in the default warehouse."
 *    Now matter which other products get picked together with the product, it will always be taken from the same bin
 *    location. Also the current stock distribution is not relevant.
 *
 * Example of a picking strategy that is NOT product-orthogonal:
 *    "Take the product from a bin location where there is the lowest stock"
 *    Depending on the current distribution of stock of the product in all warehouses, the target bin location could
 *    differ.
 */
interface ProductOrthogonalPickingStrategy extends PickingStrategy {}
