<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareAccountBundle\ApiClient;

use DateTimeInterface;
use GuzzleHttp\Client;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareAccountBundle\ApiClient\Model\PickwareLicense;
use Pickware\PickwareAccountBundle\ApiClient\Model\PickwareLicenseLease;
use Pickware\PickwareAccountBundle\ApiClient\Model\PickwareLicenseLeaseOptions;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PickwareAccountApiClient
{
    public function __construct(
        private readonly Client $client,
    ) {}

    public function getPickwareLicense(): PickwareLicense
    {
        $response = $this->client->get('/api/v4/plugin-license');
        $arrayResponse = Json::decodeToArray((string)$response->getBody());

        return PickwareLicense::fromArray($arrayResponse);
    }

    public function getPickwareLicenseLease(PickwareLicenseLeaseOptions $options): PickwareLicenseLease
    {
        $response = $this->client->put(
            "/api/v4/plugin-license/{$options->getLicenseUuid()}",
            [
                'json' => [
                    'installationUuid' => $options->getInstallationUuid(),
                    'shopUuid' => $options->getShopUuid(),
                    'shopUrl' => $options->getShopUrl(),
                    'serverTime' => $options->getServerTime()->format(DateTimeInterface::ATOM),
                ],
            ],
        );
        $arrayResponse = Json::decodeToArray((string)$response->getBody());

        return PickwareLicenseLease::fromArray($arrayResponse);
    }

    public function disconnectFromPickwareAccount(PickwareLicenseLeaseOptions $options): void
    {
        $this->client->put(
            "/api/v4/plugin-license/{$options->getLicenseUuid()}/disconnect",
            [
                'json' => [
                    'installationUuid' => $options->getInstallationUuid(),
                    'shopUuid' => $options->getShopUuid(),
                    'shopUrl' => $options->getShopUrl(),
                    'serverTime' => $options->getServerTime()->format(DateTimeInterface::ATOM),
                ],
            ],
        );
    }
}
