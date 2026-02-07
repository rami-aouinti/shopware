<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsellNudgingBundle\PickwareAccountInformation;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
#[AutoconfigureTag(name: PickwareAccountInformationRegistry::DI_CONTAINER_TAG)]
readonly class PickwareAccountInformation
{
    public function __construct(
        public string $shopUuid,
        public string $organizationUuid,
    ) {}

    /**
     * Returns an absolute path that can be used to redirect users to their Pickware Account.
     */
    public function getShopBasePath(): string
    {
        $org = urlencode($this->organizationUuid);
        $shop = urlencode($this->shopUuid);

        return "/organization/{$org}/shop/{$shop}";
    }
}
