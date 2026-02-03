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

use Pickware\DpdBundle\Api\DpdApiClientConfig;
use Pickware\ShippingBundle\Authentication\CachedTokenRetriever;
use Pickware\ShippingBundle\Soap\SoapRequest;
use Pickware\ShippingBundle\Soap\SoapRequestHandler;
use SoapFault;
use SoapHeader;
use stdClass;

class DpdShipmentServiceApiAuthRequestHandler implements SoapRequestHandler
{
    public function __construct(
        private readonly CachedTokenRetriever $cachedTokenRetriever,
        private readonly DpdApiClientConfig $dpdApiClientConfig,
    ) {}

    public function handle(SoapRequest $request, callable $next): stdClass
    {
        $request = $request->addingHeaders($this->getAuthSoapHeader());

        try {
            return $next($request);
        } catch (SoapFault $soapFault) {
            $authenticationFault = $soapFault->detail?->authenticationFault ?? null;
            if ($authenticationFault !== null) {
                $this->cachedTokenRetriever->invalidateCache($this->dpdApiClientConfig);
                $request = $request->addingHeaders($this->getAuthSoapHeader());

                return $next($request);
            }

            throw $soapFault;
        }
    }

    /**
     * @return SoapHeader[]
     */
    private function getAuthSoapHeader(): array
    {
        // Shopware uses the format "de-DE" but DPD expects "de_DE"
        $userLocaleCode = str_replace('-', '_', $this->dpdApiClientConfig->getLocaleCode());

        $token = $this->cachedTokenRetriever->retrieveToken($this->dpdApiClientConfig);

        $auth = [
            'delisId' => $this->dpdApiClientConfig->getDelisId(),
            'authToken' => $token->getStringRepresentation(),
            'messageLanguage' => $userLocaleCode,
        ];
        $authHeader = new SoapHeader(
            'http://dpd.com/common/service/types/Authentication/2.0',
            'authentication',
            $auth,
            false,
        );

        return [$authHeader];
    }
}
