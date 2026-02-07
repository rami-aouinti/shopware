<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\ParcelHydration;

readonly class ParcelHydrationConfiguration
{
    /**
     * @param ?array $filterProducts An array of products to filter the parcel items by.
     *     Each element is an associative array with the following structure:
     *     [
     *         'productId' => string, // The ID of the product
     *         'quantity' => int,     // The quantity of the product to include
     *     ]
     *     If null, all products in the order will be included.
     */
    public function __construct(private ?array $filterProducts) {}

    public function getFilterProducts(): ?array
    {
        return $this->filterProducts;
    }
}
