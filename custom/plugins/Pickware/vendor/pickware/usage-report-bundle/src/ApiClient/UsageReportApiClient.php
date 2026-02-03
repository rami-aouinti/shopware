<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UsageReportBundle\ApiClient;

use GuzzleHttp\Client;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\UsageReportBundle\ApiClient\Model\PickwareShop;
use Pickware\UsageReportBundle\ApiClient\Model\UsageReport;
use Pickware\UsageReportBundle\ApiClient\Model\UsageReportRegistrationResponse;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class UsageReportApiClient implements UsageReportApiClientInterface
{
    public function __construct(
        private readonly Client $client,
    ) {}

    /**
     * @param ImmutableCollection<UsageReport> $usageReports
     * @return ImmutableCollection<UsageReportRegistrationResponse>
     */
    public function registerUsageReports(
        ImmutableCollection $usageReports,
        PickwareShop $pickwareShop,
        ?string $licenseUuid,
    ): ImmutableCollection {
        $response = $this->client->put(
            "/api/v4/organization/{$pickwareShop->getOrganizationUuid()}/shop/{$pickwareShop->getShopUuid()}/usage-report",
            [
                'json' => [
                    'usageReports' => $usageReports,
                    ...($licenseUuid !== null ? [
                        'pluginLicenseUuid' => $licenseUuid,
                    ] : []),
                ],
            ],
        );
        $arrayResponse = Json::decodeToArray((string)$response->getBody());

        $usages = $arrayResponse['registeredUsageReports'];

        return ImmutableCollection::fromArray($usages, UsageReportRegistrationResponse::class);
    }
}
