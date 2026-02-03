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
class CashOnDeliveryServiceOption extends AbstractShipmentServiceOption
{
    public function __construct(
        private readonly float $amount,
        private readonly string $currency,
        private readonly string $bankAccountData,
    ) {}

    public function applyToShipmentArray(array &$shipmentArray): void
    {
        $international = !(new Country($shipmentArray['OURecipientAddress']['CountryID']))->equals(new Country('AT'));

        $additionalRow = [
            'Name' => 'CashOnDelivery',
            'ThirdPartyID' => $international ? '022' : '006', // CashOnDelivery Service Codes International: 022, National: 006
            'Value1' => $this->amount,
            'Value2' => $this->currency,
            'Value3' => $this->bankAccountData,
            'Value4' => $shipmentArray['OUShipperReference1'],
        ];

        $shipmentArray['FeatureList']['AdditionalInformationRow'][] = $additionalRow;
    }
}
