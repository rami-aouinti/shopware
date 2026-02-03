<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Api;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Pickware\HttpUtils\Sanitizer\HeaderSanitizer;
use Pickware\HttpUtils\Sanitizer\HttpSanitizing;
use Pickware\ShippingBundle\Http\HttpLogger;
use Pickware\ShippingBundle\Rest\BadResponseExceptionHandlingMiddleware;
use Pickware\ShippingBundle\Rest\GuzzleLoggerMiddleware;
use Pickware\ShippingBundle\Rest\RestApiClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DhlExpressApiClientFactory
{
    private const BASE_URL_PRODUCTION = 'https://express.api.dhl.com/mydhlapi/';
    private const BASE_URL_TESTING = 'https://express.api.dhl.com/mydhlapi/test/';

    public function __construct(
        #[Autowire(service: 'monolog.logger.pickware_dhl_express')]
        private readonly LoggerInterface $logger,
    ) {}

    public function createDhlExpressApiClient(DhlExpressApiClientConfig $config): RestApiClient
    {
        $handlerStack = HandlerStack::create();
        $handlerStack->unshift(new GuzzleLoggerMiddleware(new HttpLogger(
            $this->logger,
            new HttpSanitizing(HeaderSanitizer::createForDefaultAuthHeaders()),
        )));
        $handlerStack->unshift(new BadResponseExceptionHandlingMiddleware(
            [
                DhlExpressApiClientException::class,
                'fromClientException',
            ],
            [
                DhlExpressApiClientException::class,
                'fromServerException',
            ],
        ));

        $restClient = $this->createClient([
            'base_uri' => $config->shouldUseTestingEndpoint() ? self::BASE_URL_TESTING : self::BASE_URL_PRODUCTION,
            'auth' => [
                $config->getUsername(),
                $config->getPassword(),
            ],
            'handler' => $handlerStack,
        ]);

        return new RestApiClient($restClient);
    }

    protected function createClient(array $config): Client
    {
        return new Client($config);
    }
}
