<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Api\Handler;

use Pickware\DpdBundle\Api\DpdApiClientException;
use Pickware\ShippingBundle\Soap\SoapRequest;
use Pickware\ShippingBundle\Soap\SoapRequestHandler;
use SoapFault;
use stdClass;

class DpdShipmentServiceApiErrorHandlingRequestHandler implements SoapRequestHandler
{
    public function handle(SoapRequest $request, callable $next): stdClass
    {
        try {
            $response = $next($request);
        } catch (SoapFault $soapFault) {
            throw DpdApiClientException::dpdShipmentServiceApiCommunicationException($soapFault);
        }

        return $response;
    }
}
