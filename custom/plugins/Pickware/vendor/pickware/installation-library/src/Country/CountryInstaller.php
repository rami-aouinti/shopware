<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\Country;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;

class CountryInstaller
{
    public function __construct(private readonly EntityManager $entityManager) {}

    public function installCountry(Country $country, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($country, $context): void {
            $this->ensureCountryExists($country, $context);
        });
    }

    public function ensureCountryExists(Country $country, Context $context): string
    {
        /** @var CountryEntity|null $existingCountry */
        $existingCountry = $this->entityManager->findOneBy(
            CountryDefinition::class,
            ['iso' => $country->getIso2()],
            $context,
        );
        $countryId = $existingCountry ? $existingCountry->getId() : Uuid::randomHex();

        $this->entityManager->upsert(
            CountryDefinition::class,
            [
                [
                    'id' => $countryId,
                    'name' => $country->getTranslatesName(),
                    'iso' => $country->getIso2(),
                    'iso3' => $country->getIso3(),
                    'position' => $country->getPosition(),
                ],
            ],
            $context,
        );

        return $countryId;
    }
}
