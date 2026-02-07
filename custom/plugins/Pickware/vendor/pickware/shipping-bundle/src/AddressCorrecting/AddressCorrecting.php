<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\AddressCorrecting;

use Pickware\ShippingBundle\Shipment\Address;

/**
 * Implement this interface to automatically correct addresses in shipping blueprints before they are sent to the
 * frontend. This can be used to fix common spelling mistakes or formatting errors. The corrections should be
 * fail-safe to avoid breaking an address of a customer.
 */
interface AddressCorrecting
{
    /**
     * Corrects an address. Make sure to return a new address object instead of modifying the given parameter, if you
     * make any changes. Return the unmodified address object otherwise.
     */
    public function correctAddress(Address $address): Address;
}
