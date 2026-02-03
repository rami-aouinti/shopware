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

use Pickware\AustrianPostBundle\Config\AustrianPostPrinterConfig;
use Pickware\ShippingBundle\Soap\SoapRequest;
use Pickware\ShippingBundle\Soap\SoapRequestHandler;
use stdClass;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AustrianPostShipmentServicePrinterConfigHandler implements SoapRequestHandler
{
    public function __construct(private readonly AustrianPostPrinterConfig $printerConfig) {}

    public function handle(SoapRequest $soapRequest, callable $next): stdClass
    {
        $body = $soapRequest->getBody();
        $body['row']['PrinterObject'] = $this->printerConfig->getPrinterObject();

        return $next(new SoapRequest($soapRequest->getMethod(), $body));
    }
}
