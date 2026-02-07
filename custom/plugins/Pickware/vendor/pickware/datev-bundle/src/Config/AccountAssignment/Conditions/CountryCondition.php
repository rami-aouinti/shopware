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
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class CountryCondition implements RevenueAccountCondition
{
    public function __construct(private readonly string $countryIsoCode) {}

    /**
     * @param RevenueAccountRequestItem $item
     */
    public function matches($item): bool
    {
        return $this->countryIsoCode === $item->getCountryIsoCode();
    }
}
