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

use Pickware\AustrianPostBundle\Api\Handler\AustrianPostShipmentServiceApiErrorHandlingRequestHandler;
use Pickware\AustrianPostBundle\Api\Handler\AustrianPostShipmentServiceCredentialsHandler;
use Pickware\AustrianPostBundle\Api\Handler\AustrianPostShipmentServicePrinterConfigHandler;
use Pickware\AustrianPostBundle\Config\AustrianPostConfig;
use Pickware\HttpUtils\Sanitizer\HeaderSanitizer;
use Pickware\HttpUtils\Sanitizer\HttpSanitizerCollection;
use Pickware\HttpUtils\Sanitizer\XmlSanitizer;
use Pickware\ShippingBundle\Soap\RequestHandler\SoapRequestLoggingHandler;
use Pickware\ShippingBundle\Soap\SoapApiClient;
use Psr\Log\LoggerInterface;
use SoapClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AustrianPostSoapApiClientFactory
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.pickware_austrian_post')]
        private readonly LoggerInterface $austrianPostRequestLogger,
    ) {}

    public function createAuthenticatedAustrianPostShipmentServiceApiClient(
        AustrianPostApiClientConfig $apiClientConfig,
    ): SoapApiClient {
        $soapApiClient = $this->createAustrianPostSoapClient(
            $apiClientConfig->shouldUseTestingEndpoint(),
        );

        $soapApiClient->use(
            new AustrianPostShipmentServiceCredentialsHandler($apiClientConfig),
            new AustrianPostShipmentServiceApiErrorHandlingRequestHandler(),
            new SoapRequestLoggingHandler(
                $soapApiClient->getSoapClient(),
                $this->austrianPostRequestLogger,
                new HttpSanitizerCollection(
                    new XmlSanitizer(
                        namespaceName: 'austrianPost',
                        namespaceUri: 'http://post.ondot.at',
                        hiddenXmlPaths: [
                            '//austrianPost:*/austrianPost:ClientID',
                            '//austrianPost:*/austrianPost:OrgUnitID',
                            '//austrianPost:*/austrianPost:OrgUnitGuid',
                        ],
                        truncatedXmlPaths: [
                            '//austrianPost:pdfData',
                            '//austrianPost:shipmentDocuments',
                        ],
                    ),
                    HeaderSanitizer::createForDefaultAuthHeaders(),
                ),
            ),
        );

        return $soapApiClient;
    }

    public function createConfiguredAustrianPostShipmentServiceApiClient(
        AustrianPostConfig $austrianPostConfig,
    ): SoapApiClient {
        $soapApiClient = $this->createAuthenticatedAustrianPostShipmentServiceApiClient(
            $austrianPostConfig->getApiClientConfig(),
        );

        $soapApiClient->prependHandlers(
            new AustrianPostShipmentServicePrinterConfigHandler($austrianPostConfig->getPrinterSettings()),
        );

        return $soapApiClient;
    }

    protected function createAustrianPostSoapClient(
        bool $useTestingEndpoint,
    ): SoapApiClient {
        if ($useTestingEndpoint) {
            $wsdlFileName = sprintf(
                '%1$s/WsdlDocuments/ShippingService/ShippingServiceTesting.wsdl',
                __DIR__,
            );
        } else {
            $wsdlFileName = sprintf(
                '%1$s/WsdlDocuments/ShippingService/ShippingServiceProd.wsdl',
                __DIR__,
            );
        }

        $soapClient = $this->createSoapClient(
            $wsdlFileName,
            [
                'soap_version' => SOAP_1_1,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 10,
                'trace' => true,
            ],
        );

        return new SoapApiClient($soapClient);
    }

    protected function createSoapClient(string $wsdlFileName, array $soapOptions): SoapClient
    {
        return new SoapClient($wsdlFileName, $soapOptions);
    }
}
