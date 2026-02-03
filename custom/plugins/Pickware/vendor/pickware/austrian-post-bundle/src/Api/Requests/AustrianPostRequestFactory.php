<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Api\Requests;

use Pickware\AustrianPostBundle\Api\AustrianPostShipment;
use Pickware\ShippingBundle\Shipment\Model\TrackingCodeCollection;
use Pickware\ShippingBundle\Shipment\Model\TrackingCodeEntity;
use Pickware\ShippingBundle\Soap\SoapRequest;

class AustrianPostRequestFactory
{
    public static function makeImportShipmentRequest(AustrianPostShipment $austrianPostShipment): SoapRequest
    {
        return new SoapRequest('ImportShipment', ['row' => $austrianPostShipment->toArray()]);
    }

    public static function makeCancelShipmentRequest(TrackingCodeCollection $trackingCodes): SoapRequest
    {
        return new SoapRequest(
            'CancelShipments',
            [
                'shipments' => [
                    'CancelShipmentRow' => [
                        'Number' => $trackingCodes->first()?->getTrackingCode(),
                        'ColloCodeList' => array_values(array_map(
                            fn(TrackingCodeEntity $trackingCode) => $trackingCode->getTrackingCode(),
                            $trackingCodes->getElements(),
                        )),
                    ],
                ],
            ],
        );
    }
}
