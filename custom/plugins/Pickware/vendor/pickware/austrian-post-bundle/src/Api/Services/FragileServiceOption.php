<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Api\Services;

use Pickware\ShippingBundle\Shipment\Country;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class FragileServiceOption extends AbstractShipmentServiceOption
{
    public function applyToShipmentArray(array &$shipmentArray): void
    {
        $international = !(new Country($shipmentArray['OURecipientAddress']['CountryID']))->equals(new Country('AT'));

        $additionalRow = [
            'Name' => 'Fragile',
            'ThirdPartyID' => $international ? '024' : '004',
        ];

        $shipmentArray['FeatureList']['AdditionalInformationRow'][] = $additionalRow;
    }
}
