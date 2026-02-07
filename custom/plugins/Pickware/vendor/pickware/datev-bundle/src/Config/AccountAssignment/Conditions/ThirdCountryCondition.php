<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\AccountAssignment\Conditions;

use Pickware\DatevBundle\Config\AccountAssignment\Item\RevenueAccountRequestItem;
use Pickware\DatevBundle\PickwareDatevBundle;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ThirdCountryCondition implements RevenueAccountCondition
{
    public function __construct() {}

    /**
     * @param RevenueAccountRequestItem $item
     */
    public function matches($item): bool
    {
        return $item->getCountryIsoCode() !== null
            && $item->getCountryIsoCode() !== PickwareDatevBundle::PICKWARE_SHOPIFY_UNKNOWN_COUNTRY_ISO_CODE
            && !in_array(
                $item->getCountryIsoCode(),
                PickwareDatevBundle::ISO_CODES_OF_DESTINATION_COUNTRIES_OF_EUROPEAN_UNION_DELIVERIES,
                true,
            );
    }
}
