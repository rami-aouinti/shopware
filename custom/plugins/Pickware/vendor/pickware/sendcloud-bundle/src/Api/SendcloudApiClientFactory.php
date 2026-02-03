<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SendcloudBundle\Api;

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

class SendcloudApiClientFactory
{
    private const BASE_URL_PRODUCTION = 'https://panel.sendcloud.sc/api/v2/';

    public function __construct(
        #[Autowire(service: 'monolog.logger.pickware_sendcloud')]
        private readonly LoggerInterface $logger,
    ) {}

    public function createSendcloudApiClient(SendcloudApiClientConfig $config): RestApiClient
    {
        $handlerStack = HandlerStack::create();
        $handlerStack->unshift(new GuzzleLoggerMiddleware(new HttpLogger(
            $this->logger,
            new HttpSanitizing(HeaderSanitizer::createForDefaultAuthHeaders()),
        )));
        $handlerStack->unshift(new BadResponseExceptionHandlingMiddleware(
            [
                SendcloudApiClientException::class,
                'fromClientException',
            ],
            [
                SendcloudApiClientException::class,
                'fromServerException',
            ],
        ));
        $handlerStack->unshift(PartnerIdHeaderMiddleware::createForPickware());

        $restClient = $this->createClient([
            'base_uri' => self::BASE_URL_PRODUCTION,
            'auth' => [
                $config->getPublicKey(),
                $config->getSecretKey(),
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
