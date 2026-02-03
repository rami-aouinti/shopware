<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Api;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Pickware\DsvBundle\Api\Handler\DsvApiKeyMiddleware;
use Pickware\DsvBundle\Api\Handler\DsvOAuthMiddleware;
use Pickware\HttpUtils\Sanitizer\HeaderSanitizer;
use Pickware\HttpUtils\Sanitizer\HttpSanitizing;
use Pickware\ShippingBundle\Authentication\PrivateFileSystemCachedTokenRetriever;
use Pickware\ShippingBundle\Http\HttpLogger;
use Pickware\ShippingBundle\Rest\BadResponseExceptionHandlingMiddleware;
use Pickware\ShippingBundle\Rest\GuzzleLoggerMiddleware;
use Pickware\ShippingBundle\Rest\RestApiClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DsvApiClientFactory
{
    private const BASE_TESTING_URL = 'https://api.dsv.com/my-demo/';
    private const BASE_PRODUCTION_URL = 'https://api.dsv.com/my/';
    private const TESTING_API_KEY_MAPPING = [
        '/my-demo/booking/v2/bookings' => '266b22e953bb45b6a117b34dcab088ad',
        '/my-demo/printing/v1/labels' => '0fb9c72353e04da9afcef31b8975a8e5',
        '/my-demo/tracking/v2/shipments' => '8a1bb4a8b1f649debd3de65c8cd453f9',
    ];
    private const PRODUCTION_API_KEY_MAPPING = [
        '/my/booking/v2/bookings' => '267aa6622daf40c4bf0132a71db48f9f',
        '/my/printing/v1/labels' => 'd942b891d631435091239124653c88d1',
        '/my/tracking/v2/shipments' => '5699ee432ef14786a7972a47e04913cd',
    ];

    public function __construct(
        #[Autowire(service: 'monolog.logger.pickware_dsv')]
        private readonly LoggerInterface $logger,
        #[Autowire(service: 'pickware-dsv.private_file_system_cached_token_retriever')]
        private readonly PrivateFileSystemCachedTokenRetriever $privateFileSystemCachedTokenRetriever,
    ) {}

    public function createDsvApiClient(DsvApiClientConfig $config): RestApiClient
    {
        $handlerStack = HandlerStack::create();
        $handlerStack->unshift(new GuzzleLoggerMiddleware(new HttpLogger(
            $this->logger,
            new HttpSanitizing(
                new HeaderSanitizer([
                    'dsv-service-auth',
                    'dsv-subscription-key',
                ]),
            ),
        )));
        $handlerStack->unshift(new BadResponseExceptionHandlingMiddleware(
            [
                DsvApiClientException::class,
                'fromClientException',
            ],
            [
                DsvApiClientException::class,
                'fromServerException',
            ],
        ));
        $handlerStack->unshift(new DsvApiKeyMiddleware(
            $config->shouldUseTestingEndpoint() ? self::TESTING_API_KEY_MAPPING : self::PRODUCTION_API_KEY_MAPPING,
        ));

        $handlerStack->unshift(
            new DsvOAuthMiddleware(
                dsvApiClientConfig: $config,
                tokenRetriever: $this->privateFileSystemCachedTokenRetriever,
            ),
        );
        $headers = [
            'Content-Type' => 'application/json',
        ];

        $restClient = $this->createClient([
            'base_uri' => $config->shouldUseTestingEndpoint() ? self::BASE_TESTING_URL : self::BASE_PRODUCTION_URL,
            'headers' => $headers,
            'handler' => $handlerStack,
        ]);

        return new RestApiClient($restClient);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function createClient(array $config): Client
    {
        return new Client($config);
    }
}
