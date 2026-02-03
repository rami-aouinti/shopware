<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Soap\RequestHandler;

use Pickware\ShippingBundle\Soap\SoapRequest;
use Pickware\ShippingBundle\Soap\SoapRequestHandler;
use stdClass;

/**
 * Disables the compression optimization of the PHP SoapClient
 *
 * What is the compression optimization?
 *  Before sending the XML, the PHP SoapClient automatically removes duplicated elements from the XML and replaces
 *  them with references.
 *
 *  Example:
 *      <ns1:ChargeBU id="ref1">
 *          <ns1:ChargeBreakUp>
 *              <ns1:PriceId>0</ns1:PriceId>
 *              <ns1:Amount>35</ns1:Amount>
 *          </ns1:ChargeBreakUp>
 *          <ns1:ChargeBreakUp>
 *              <ns1:PriceId>0</ns1:PriceId>
 *              <ns1:Amount>35</ns1:Amount>
 *          </ns1:ChargeBreakUp>
 *      </ns1:ChargeBU>
 *    becomes:
 *      <ns1:ChargeBU>
 *          <ns1:ChargeBreakUp id="ref1">
 *              <ns1:PriceId>0</ns1:PriceId>
 *              <ns1:Amount>35</ns1:Amount>
 *          </ns1:ChargeBreakUp>
 *          <ns1:ChargeBreakUp href="ref1" />
 *      </ns1:ChargeBU>
 *
 * Why it this class necessary?
 *  The PHP SoapClient has no option to disable this optimization but some servers do not support them.
 *  Hence the request has to be "manipulated" so the optimization algorithm cannot be applied.
 *
 * How does it work:
 *   It adds <randomElement> XML elements to every element of the origin XML. This elements contain a random number as
 *   its inner text. This causes that the optimization algorithm to have no effect.
 *   Before the XML is sent to the server, the PHP Soap client removes that elements because they do not appear in the
 *   XML schema.
 */
class AntiCompressionSoapRequestHandler implements SoapRequestHandler
{
    public function handle(SoapRequest $soapRequest, callable $next): stdClass
    {
        $body = $soapRequest->getBody();

        $this->fillWithRandomElements($body);

        return $next(new SoapRequest($soapRequest->getMethod(), $body));
    }

    private function fillWithRandomElements(&$body): void
    {
        static $i = 0;
        if (!is_array($body)) {
            return;
        }
        $keys = array_keys($body);
        if (count($keys) === 0) {
            return;
        }
        if (is_string($keys[0])) {
            $body['randomElement'] = $i++;
        }
        foreach ($body as &$value) {
            $this->fillWithRandomElements($value);
        }
    }
}
