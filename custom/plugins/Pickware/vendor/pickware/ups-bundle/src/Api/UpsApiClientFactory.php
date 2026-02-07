<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Api;

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

class UpsApiClientFactory
{
    private const BASE_URL_PRODUCTION = 'https://onlinetools.ups.com/api/shipments/v1/';
    private const BASE_URL_TESTING = 'https://wwwcie.ups.com/api/shipments/v1/';

    public function __construct(
        #[Autowire(service: 'monolog.logger.pickware_ups')]
        private readonly LoggerInterface $logger,
        private readonly UpsOAuthApiClientFactory $upsOAuthApiClientFactory,
    ) {}

    public function createUpsApiClient(UpsApiClientConfig $config): RestApiClient
    {
        $handlerStack = HandlerStack::create();
        $handlerStack->unshift(new GuzzleLoggerMiddleware(new HttpLogger(
            $this->logger,
            new HttpSanitizing(HeaderSanitizer::createForDefaultAuthHeaders()),
        )));
        $handlerStack->unshift(new BadResponseExceptionHandlingMiddleware(
            [
                UpsApiClientException::class,
                'fromClientException',
            ],
            [
                UpsApiClientException::class,
                'fromServerException',
            ],
        ));
        $handlerStack->unshift(TransactionHeaderMiddleware::createForPickware());
        $handlerStack->unshift(
            new OAuthMiddleware(
                $config,
                $this->upsOAuthApiClientFactory->createUpsOAuthApiClient($config->shouldUseTestingEndpoint()),
            ),
        );

        $restClient = $this->createClient([
            'base_uri' => $config->shouldUseTestingEndpoint() ? self::BASE_URL_TESTING : self::BASE_URL_PRODUCTION,
            'handler' => $handlerStack,
        ]);

        return new RestApiClient($restClient);
    }

    protected function createClient(array $config): Client
    {
        return new Client($config);
    }
}
