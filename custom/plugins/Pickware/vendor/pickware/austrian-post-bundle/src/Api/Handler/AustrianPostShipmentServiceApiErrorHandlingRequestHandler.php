<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Api\Handler;

use Pickware\AustrianPostBundle\Api\AustrianPostShipmentServiceApiApplicationException;
use Pickware\AustrianPostBundle\Api\AustrianPostShipmentServiceApiTransportException;
use Pickware\ShippingBundle\Soap\SoapRequest;
use Pickware\ShippingBundle\Soap\SoapRequestHandler;
use SoapFault;
use stdClass;

class AustrianPostShipmentServiceApiErrorHandlingRequestHandler implements SoapRequestHandler
{
    public function handle(SoapRequest $request, callable $next): stdClass
    {
        try {
            $response = $next($request);
        } catch (SoapFault $soapFault) {
            throw new AustrianPostShipmentServiceApiTransportException($soapFault);
        }

        if (isset($response->errorCode)) {
            throw new AustrianPostShipmentServiceApiApplicationException(
                errorCode: $response->errorCode,
                errorMessage: $response->errorMessage,
            );
        }

        if (isset($response->CancelShipmentsResult) && $response->CancelShipmentsResult->CancelShipmentResult->CancelSuccessful === false) {
            throw new AustrianPostShipmentServiceApiApplicationException(
                errorCode: $response->CancelShipmentsResult->CancelShipmentResult->ErrorCode,
                errorMessage: $response->CancelShipmentsResult->CancelShipmentResult->ErrorMessage,
            );
        }

        return $response;
    }
}
