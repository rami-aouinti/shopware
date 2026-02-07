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

use InvalidArgumentException;
use Pickware\ShippingBundle\Shipment\Address;

class AddressCorrectingService
{
    public function __construct(
        private readonly iterable $addressCorrections,
    ) {
        foreach ($this->addressCorrections as $addressCorrection) {
            if (!($addressCorrection instanceof AddressCorrecting)) {
                throw new InvalidArgumentException(sprintf(
                    "Service %s tagged with 'pickware_shipping_bundle.address_correcting' needs to implement the %s interface.",
                    $addressCorrection::class,
                    AddressCorrecting::class,
                ));
            }
        }
    }

    public function correctAddress(Address $address): Address
    {
        foreach ($this->addressCorrections as /** @var AddressCorrecting $addressCorrection */ $addressCorrection) {
            $address = $addressCorrection->correctAddress($address);
        }

        return $address;
    }
}
