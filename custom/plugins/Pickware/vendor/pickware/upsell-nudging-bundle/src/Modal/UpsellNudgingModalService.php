<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsellNudgingBundle\Modal;

use League\Uri\Http;
use Pickware\UpsellNudgingBundle\PickwareAccountInformation\PickwareAccountInformationRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class UpsellNudgingModalService
{
    public function __construct(
        #[Autowire('%env(APP_URL)%')]
        private string $appUrl,
        #[Autowire(param: 'pickware_upsell_nudging.business_platform_base_url')]
        private string $pickwareAccountUrl,
        private PickwareAccountInformationRegistry $pickwareAccountInformationRegistry,
    ) {}

    /**
     * Creates a link to the Pickware Account upgrade page for the given feature name.
     *
     * @param string $featureName The name of the feature for which the upgrade link is created.
     * @return string The link to the Pickware Account upgrade page.
     */
    public function createPickwareAccountUpgradeLink(string $featureName): string
    {
        $pickwareAccountInformation = $this->pickwareAccountInformationRegistry->getPickwareAccountInformation();
        if ($pickwareAccountInformation === null) {
            throw UpsellNudgingModalException::pickwareAccountNotConnected();
        }

        $appHost = Http::new($this->appUrl)->getHost();
        $queryOptions = [
            'utm_term' => $featureName,
            'utm_source' => $appHost,
            'utm_medium' => 'upsell-nudging-modal',
            'upsell_nudging_feature_name' => $featureName,
        ];

        return Http::new($this->pickwareAccountUrl)
            ->withPath(rtrim($pickwareAccountInformation->getShopBasePath(), '/') . '/subscription')
            ->withQuery(http_build_query($queryOptions, '', '&', PHP_QUERY_RFC3986))
            ->jsonSerialize();
    }
}
