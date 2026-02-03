<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Api;

use Pickware\ShippingBundle\Shipment\Country;

enum AustrianPostProduct: string
{
    case AustrianPostParcel = '10';
    case AustrianPostPremiumLight = '14';
    case AustrianPostPremiumSelect = '30';
    case AustrianPostNextDay = '65';
    case AustrianPostPlusInternational = '70';
    case AustrianPostPremiumInternational = '45';
    case AustrianPostLightInternational = '69';
    case AustrianPostCombiFreightAustria = '47';
    case AustrianPostCombiFreightInternational = '49';
    case AustrianPostPremiumAustriaB2B = '31';
    case AustrianPostExpressAustria = '01';
    case AustrianPostExpressInternational = '46';
    case AustrianPostSmallParcel2k = '96';
    case AustrianPostSmallParcel2kPlus = '16';
    case AustrianPostParcelMediumWithTracking = '78';
    case AustrianPostReturnShipmentAustria = '28';
    case AustrianPostReturnShipmentInternational = '63';

    public function getThirdPartyId(): string
    {
        return $this->value;
    }

    public static function getProductForReturnShipmentFromCountry(Country $country): self
    {
        return $country->equals(new Country('AT')) ? self::AustrianPostReturnShipmentAustria : self::AustrianPostReturnShipmentInternational;
    }
}
