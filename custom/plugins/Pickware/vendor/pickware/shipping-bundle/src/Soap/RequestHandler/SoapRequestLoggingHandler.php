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

use LogicException;
use Pickware\HttpUtils\Sanitizer\HttpSanitizer;
use Pickware\ShippingBundle\Soap\SoapRequest;
use Pickware\ShippingBundle\Soap\SoapRequestHandler;
use Psr\Log\LoggerInterface;
use SoapClient;
use SoapFault;
use stdClass;

/**
 * Logs requests/response of a SoapRequest to a logger
 */
class SoapRequestLoggingHandler implements SoapRequestHandler
{
    private SoapClient $soapClient;
    private LoggerInterface $logger;
    private HttpSanitizer $httpRequestSanitizer;

    public function __construct(
        SoapClient $soapClient,
        LoggerInterface $logger,
        HttpSanitizer $httpRequestSanitizer,
    ) {
        $this->soapClient = $soapClient;
        $this->logger = $logger;
        $this->httpRequestSanitizer = $httpRequestSanitizer;
    }

    public function handle(SoapRequest $request, callable $next): stdClass
    {
        try {
            $response = $next($request);

            $this->logger->debug(
                'SOAP action executed successfully',
                [
                    'action' => $request->getMethod(),
                    'request' => $this->getLastSoapRequestAsArray(),
                ],
            );

            $this->ensureTracingIsEnabledInSoapClient();

            return $response;
        } catch (SoapFault $soapFault) {
            $message = sprintf(
                'SOAP action failed with code %s: %s',
                $soapFault->faultcode,
                $soapFault->faultstring,
            );

            $this->logger->warning(
                $message,
                [
                    'action' => $request->getMethod(),
                    'request' => $this->getLastSoapRequestAsArray(),
                    'SoapFault' => [
                        'code' => $soapFault->faultcode,
                        // SIC: the property is named "_name", not "faultname" as the docs say
                        'name' => $soapFault->_name ?? null,
                        'detail' => $soapFault->detail ?? null,
                        'actor' => $soapFault->faultactor ?? null,
                        'header' => $soapFault->headerfault ?? null,
                    ],
                ],
            );

            throw $soapFault;
        }
    }

    private function getLastSoapRequestAsArray(): array
    {
        // In case the request could not be completed, e.g. because the server is offline, the following values are null
        $requestHeaders = $this->soapClient->__getLastRequestHeaders() ?: '';
        $requestBody = $this->soapClient->__getLastRequest() ?: '';
        $responseHeaders = $this->soapClient->__getLastResponseHeaders() ?: '';
        $responseBody = $this->soapClient->__getLastResponse() ?: '';

        return [
            'requestHeader' => $this->getHeaderArray($requestHeaders),
            'requestBody' => $this->httpRequestSanitizer->filterBody($requestBody),
            'responseHeader' => $this->getHeaderArray($responseHeaders),
            'responseBody' => $this->httpRequestSanitizer->filterBody($responseBody),
        ];
    }

    private function getHeaderArray(string $headerString): array
    {
        $headers = explode("\n", $headerString);
        $headerKvPairs = [];
        foreach ($headers as $header) {
            $headerParts = explode(':', $header, 2);
            if (count($headerParts) !== 2) {
                continue;
            }
            $headerParts = array_map('trim', $headerParts);
            [$headerName, $headerValue] = $headerParts;
            $headerKvPairs[$headerName] = $this->httpRequestSanitizer->filterHeader(
                $headerName,
                $headerValue,
            );
        }

        return $headerKvPairs;
    }

    private function ensureTracingIsEnabledInSoapClient(): void
    {
        if ($this->soapClient->__getLastRequest() === null) {
            throw new LogicException(sprintf(
                'The last request sent in the SOAP client is not set. The instance of %s passed the to the ' .
                'instance of %s was probably not created with the option trace = true. SOAP request/response pairs ' .
                'can only be logged if the SOAP client is created with this option. Check where the SoapClient is ' .
                'instantiated and ensure that the assoc array passed as the $option argument contains an element ' .
                '"trace" => true.',
                SoapClient::class,
                self::class,
            ));
        }
    }
}
