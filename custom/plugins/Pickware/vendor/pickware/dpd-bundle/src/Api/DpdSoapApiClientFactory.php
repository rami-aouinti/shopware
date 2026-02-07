<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Api;

use Pickware\DpdBundle\Api\Handler\DpdShipmentServiceApiAuthRequestHandler;
use Pickware\DpdBundle\Api\Handler\DpdShipmentServiceApiErrorHandlingRequestHandler;
use Pickware\HttpUtils\Sanitizer\HeaderSanitizer;
use Pickware\HttpUtils\Sanitizer\HttpSanitizerCollection;
use Pickware\HttpUtils\Sanitizer\XmlSanitizer;
use Pickware\ShippingBundle\Authentication\PrivateFileSystemCachedTokenRetriever;
use Pickware\ShippingBundle\Soap\RequestHandler\SoapRequestLoggingHandler;
use Pickware\ShippingBundle\Soap\SoapApiClient;
use Psr\Log\LoggerInterface;
use SoapClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DpdSoapApiClientFactory
{
    private const SHIPMENT_SERVICE_API_VERSION = 'V4_4';
    private const SHIPMENT_SERVICE_NAME = 'ShipmentService';
    private const PRODUCTION_BASE_URL = 'https://public-ws.dpd.com';
    private const TEST_BASE_URL = 'https://public-ws-stage.dpd.com';

    public function __construct(
        #[Autowire(service: 'monolog.logger.pickware_dpd')]
        private readonly LoggerInterface $dpdRequestLogger,
        #[Autowire(service: 'pickware-dpd.private_file_system_cached_token_retriever')]
        private readonly PrivateFileSystemCachedTokenRetriever $privateFileSystemCachedTokenRetriever,
    ) {}

    public function createDpdShipmentServiceApiClient(DpdApiClientConfig $dpdApiClientConfig): SoapApiClient
    {
        $shipmentServiceWsdlFileName = sprintf(
            '%1$s/WsdlDocuments/shipmentservice/shipmentservice-api-%2$s.wsdl',
            __DIR__,
            self::SHIPMENT_SERVICE_API_VERSION,
        );
        $soapApiClient = $this->createDpdSoapClient(
            $shipmentServiceWsdlFileName,
            self::SHIPMENT_SERVICE_NAME,
            self::SHIPMENT_SERVICE_API_VERSION,
            $dpdApiClientConfig,
        );

        $soapApiClient->use(
            new DpdShipmentServiceApiErrorHandlingRequestHandler(),
            new DpdShipmentServiceApiAuthRequestHandler(
                $this->privateFileSystemCachedTokenRetriever,
                $dpdApiClientConfig,
            ),
            new SoapRequestLoggingHandler(
                $soapApiClient->getSoapClient(),
                $this->dpdRequestLogger,
                new HttpSanitizerCollection(
                    new XmlSanitizer(
                        namespaceName: 'dpdAuth',
                        namespaceUri: 'http://dpd.com/common/service/types/Authentication/2.0',
                        hiddenXmlPaths: [
                            '//dpdAuth:authentication/authToken',
                        ],
                        truncatedXmlPaths: [
                            '//orderResult/output/content',
                            '//orderResult/shipmentResponses/parcelInformation/output/content',
                        ],
                    ),
                    HeaderSanitizer::createForDefaultAuthHeaders(),
                ),
            ),
        );

        return $soapApiClient;
    }

    protected function createDpdSoapClient(
        string $wsdlFileName,
        string $serviceName,
        string $serviceVersion,
        DpdApiClientConfig $dpdApiConfig,
    ): SoapApiClient {
        $soapClient = $this->createSoapClient($wsdlFileName, $this->getSoapOptions(
            $serviceName,
            $serviceVersion,
            $dpdApiConfig,
        ));

        return new SoapApiClient($soapClient);
    }

    protected function createSoapClient(string $wsdlFileName, array $soapOptions): SoapClient
    {
        return new SoapClient($wsdlFileName, $soapOptions);
    }

    private function getSoapOptions(string $serviceName, string $serviceVersion, DpdApiClientConfig $dpdApiClientConfig): array
    {
        return [
            'soap_version' => SOAP_1_1,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'trace' => true,
            'location' => sprintf(
                '%s/services/%s/%s/',
                $dpdApiClientConfig->shouldUseTestingEndpoint() ? self::TEST_BASE_URL : self::PRODUCTION_BASE_URL,
                $serviceName,
                $serviceVersion,
            ),
        ];
    }
}
