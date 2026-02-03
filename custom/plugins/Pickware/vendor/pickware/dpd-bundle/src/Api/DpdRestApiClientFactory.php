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

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Pickware\DpdBundle\Api\Handler\DpdRestApiFaultHandlingMiddleware;
use Pickware\DpdBundle\Api\Handler\DpdRestApiMessageLanguageMiddleware;
use Pickware\HttpUtils\Sanitizer\HeaderSanitizer;
use Pickware\HttpUtils\Sanitizer\HttpSanitizing;
use Pickware\ShippingBundle\Http\HttpLogger;
use Pickware\ShippingBundle\Rest\GuzzleLoggerMiddleware;
use Pickware\ShippingBundle\Rest\RestApiClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DpdRestApiClientFactory
{
    public const LOGIN_SERVICE_API_VERSION_MAJOR = 2;
    public const LOGIN_SERVICE_API_VERSION_MINOR = 0;
    public const LOGIN_SERVICE_API_VERSION_STRING = 'V' . self::LOGIN_SERVICE_API_VERSION_MAJOR . '_' . self::LOGIN_SERVICE_API_VERSION_MINOR;
    private const LOGIN_SERVICE_NAME = 'LoginService';
    private const PRODUCTION_BASE_URL = 'https://public-ws.dpd.com';
    private const TEST_BASE_URL = 'https://public-ws-stage.dpd.com';

    public function __construct(
        #[Autowire(service: 'monolog.logger.pickware_dpd')]
        private readonly LoggerInterface $dpdRequestLogger,
    ) {}

    public function createDpdLoginServiceApiClient(
        bool $useTestingEndpoint,
        string $userLocaleCode,
    ): RestApiClient {
        // Shopware uses the format "de-DE" but dpd expects "de_DE"
        $userLocaleCode = str_replace('-', '_', $userLocaleCode);

        $handlerStack = HandlerStack::create();
        $handlerStack->unshift(new GuzzleLoggerMiddleware(new HttpLogger(
            $this->dpdRequestLogger,
            new HttpSanitizing(HeaderSanitizer::createForDefaultAuthHeaders()),
        )));
        $handlerStack->unshift(new DpdRestApiFaultHandlingMiddleware());
        $handlerStack->unshift(new DpdRestApiMessageLanguageMiddleware($userLocaleCode));

        return $this->createDpdRestClient(
            self::LOGIN_SERVICE_NAME,
            self::LOGIN_SERVICE_API_VERSION_STRING,
            $useTestingEndpoint,
            $handlerStack,
        );
    }

    protected function createDpdRestClient(
        string $serviceName,
        string $serviceVersion,
        bool $useTestingEndpoint,
        ?HandlerStack $handlerStack = null,
    ): RestApiClient {
        $restClient = $this->createRestClient([
            'base_uri' => sprintf(
                '%s/restservices/%s/%s/',
                $useTestingEndpoint ? self::TEST_BASE_URL : self::PRODUCTION_BASE_URL,
                $serviceName,
                $serviceVersion,
            ),
            'handler' => $handlerStack ?? HandlerStack::create(),
            'allow_redirects' => true,
        ]);

        return new RestApiClient($restClient);
    }

    protected function createRestClient(array $config): Client
    {
        return new Client($config);
    }
}
