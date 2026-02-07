<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemodataGeneration\Patcher;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class CountriesPatcher
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function patch(Context $context): void
    {
        /** @var ImmutableCollection<CountryEntity> $countries */
        $countries = new ImmutableCollection(
            $this->entityManager->findAll(
                CountryDefinition::class,
                $context,
            ),
        );

        /** @var SalesChannelEntity $headlessSalesChannel */
        $headlessSalesChannel = $this->entityManager->getFirstBy(
            SalesChannelDefinition::class,
            ['typeId' => Defaults::SALES_CHANNEL_TYPE_API],
            [
                new FieldSorting('createdAt', FieldSorting::ASCENDING),
            ],
            $context,
        );

        $countriesToBeUpdated = $countries->compactMap(function(CountryEntity $country) use ($headlessSalesChannel) {
            if ($country->getIso() === 'DE') {
                return null;
            }

            return [
                'id' => $country->getId(),
                'companyTax' => (new TaxFreeConfig(
                    enabled: true,
                    currencyId: $country->getCompanyTax()->getCurrencyId(),
                    amount: $country->getCompanyTax()->getAmount(),
                ))->jsonSerialize(),
                'customerTax' => (new TaxFreeConfig(
                    enabled: !$country->getIsEu(),
                    currencyId: $country->getCustomerTax()->getCurrencyId(),
                    amount: $country->getCustomerTax()->getAmount(),
                ))->jsonSerialize(),
                'checkVatIdPattern' => $country->getIsEu(),
                'vatIdRequired' => $country->getIsEu(),
                'salesChannels' => [
                    [
                        'id' => $headlessSalesChannel->getId(),
                    ],
                ],
            ];
        });

        $this->entityManager->update(
            CountryDefinition::class,
            $countriesToBeUpdated->asArray(),
            $context,
        );
    }
}
