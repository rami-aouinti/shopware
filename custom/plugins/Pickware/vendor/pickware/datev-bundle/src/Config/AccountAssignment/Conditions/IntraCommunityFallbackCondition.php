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
use Pickware\DatevBundle\Config\AccountAssignment\TaxStatus;
use Pickware\DatevBundle\PickwareDatevBundle;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
/**
 * Mirrors the `IntraCommunityCondition` but requires that no hints on a buying company are given, neither through
 * given customer VAT IDs nor a billed company.
 */
class IntraCommunityFallbackCondition implements RevenueAccountCondition
{
    public function __construct() {}

    /**
     * @param RevenueAccountRequestItem $item
     */
    public function matches($item): bool
    {
        return $item->getCountryIsoCode() !== null
            && $item->getTaxStatus() === TaxStatus::TaxFree
            && !$item->hasVatId()
            && $item->getBilledCompany() === null
            && in_array(
                $item->getCountryIsoCode(),
                PickwareDatevBundle::ISO_CODES_OF_DESTINATION_COUNTRIES_OF_INTRA_COMMUNITY_DELIVERIES_FROM_GERMANY,
                strict: true,
            );
    }
}
