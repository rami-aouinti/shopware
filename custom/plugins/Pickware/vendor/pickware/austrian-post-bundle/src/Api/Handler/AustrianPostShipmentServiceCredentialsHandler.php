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

use Pickware\AustrianPostBundle\Api\AustrianPostApiClientConfig;
use Pickware\ShippingBundle\Soap\SoapRequest;
use Pickware\ShippingBundle\Soap\SoapRequestHandler;
use stdClass;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AustrianPostShipmentServiceCredentialsHandler implements SoapRequestHandler
{
    public function __construct(private readonly AustrianPostApiClientConfig $apiClientConfig) {}

    public function handle(SoapRequest $soapRequest, callable $next): stdClass
    {
        $body = $soapRequest->getBody();

        if (array_key_exists('row', $body)) {
            $body['row']['ClientID'] = $this->apiClientConfig->getClientId();
            $body['row']['OrgUnitID'] = $this->apiClientConfig->getOrgUnitId();
            $body['row']['OrgUnitGuid'] = $this->apiClientConfig->getOrgUnitGuid();
        } else {
            $body['shipments']['CancelShipmentRow']['ClientID'] = $this->apiClientConfig->getClientId();
            $body['shipments']['CancelShipmentRow']['OrgUnitID'] = $this->apiClientConfig->getOrgUnitId();
            $body['shipments']['CancelShipmentRow']['OrgUnitGuid'] = $this->apiClientConfig->getOrgUnitGuid();
        }

        return $next(new SoapRequest($soapRequest->getMethod(), $body));
    }
}
