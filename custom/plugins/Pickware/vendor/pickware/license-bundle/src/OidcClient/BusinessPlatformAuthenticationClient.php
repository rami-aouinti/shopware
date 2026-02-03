<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\OidcClient;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Pickware\PhpStandardLibrary\Json\Json;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Client for authenticating with the Pickware Business Platform using email and password credentials.
 * This is used for internal testing purposes to programmatically authenticate without browser interaction.
 */
class BusinessPlatformAuthenticationClient
{
    public function __construct(
        #[Autowire(param: 'pickware_license.business_platform_base_url')]
        private readonly string $businessPlatformBaseUrl,
    ) {}

    /**
     * Authenticates with the Business Platform using email and password.
     *
     * @throws BusinessPlatformAuthenticationException
     */
    public function login(string $email, string $password): string
    {
        $client = new Client([
            'base_uri' => $this->businessPlatformBaseUrl,
            RequestOptions::HTTP_ERRORS => false,
        ]);

        $response = $client->post('/api/v4/auth/token', [
            RequestOptions::JSON => [
                'username' => $email,
                'password' => $password,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw BusinessPlatformAuthenticationException::loginFailed(
                $response->getStatusCode(),
                (string) $response->getBody(),
            );
        }

        $responseData = Json::decodeToArray((string) $response->getBody());

        return $responseData['accessToken'];
    }
}
