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
use Pickware\HttpUtils\Sanitizer\HeaderSanitizer;
use Pickware\HttpUtils\Sanitizer\HttpSanitizing;
use Pickware\ShippingBundle\Http\HttpLogger;
use Pickware\ShippingBundle\Rest\BadResponseExceptionHandlingMiddleware;
use Pickware\ShippingBundle\Rest\GuzzleLoggerMiddleware;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DsvOAuthApiClientFactory
{
    private const BASE_TESTING_URL = 'https://api.dsv.com/my-demo/';
    private const BASE_PRODUCTION_URL = 'https://api.dsv.com/my/';
    private const TESTING_API_KEY = '7a47c07875b9467786e3c94ca44acb4d';
    private const PRODUCTION_API_KEY = 'b225ae4518cb4a36a76641876275d979';

    public function __construct(
        #[Autowire(service: 'monolog.logger.pickware_dsv')]
        private readonly LoggerInterface $logger,
        private readonly HandlerStackFactory $handlerStackFactory,
    ) {}

    public function createDsvOAuthApiClient(bool $useTestingEndpoint): Client
    {
        $handlerStack = $this->handlerStackFactory->create();
        $handlerStack->unshift(new GuzzleLoggerMiddleware(new HttpLogger(
            $this->logger,
            new HttpSanitizing(
                new HeaderSanitizer([
                    'dsv-subscription-key',
                ]),
            ),
        )));
        $handlerStack->unshift(new BadResponseExceptionHandlingMiddleware(
            DsvApiClientException::fromClientException(...),
            DsvApiClientException::fromServerException(...),
        ));

        return $this->createRestClient([
            'base_uri' => $useTestingEndpoint ? self::BASE_TESTING_URL : self::BASE_PRODUCTION_URL,
            'headers' => [
                'Content-Type' => 'application/json',
                'DSV-Subscription-Key' => $useTestingEndpoint ? self::TESTING_API_KEY : self::PRODUCTION_API_KEY,
            ],
            'handler' => $handlerStack,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function createRestClient(array $config): Client
    {
        return new Client($config);
    }
}
