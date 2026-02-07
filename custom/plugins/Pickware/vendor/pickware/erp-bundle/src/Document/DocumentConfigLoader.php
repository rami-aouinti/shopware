<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Document;

use Pickware\DalBundle\ContextFactory;
use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;

/**
 * Loads document configuration for documents that are not tied to a sales channel (e.g., supplier order documents).
 * For sales channel specific document configurations, use Shopware's DocumentConfigLoader.
 */
class DocumentConfigLoader
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ContextFactory $contextFactory,
    ) {}

    public function loadGlobalConfig(string $documentTypeTechnicalName, string $languageId, Context $context): DocumentConfiguration
    {
        /** @var DocumentBaseConfigEntity $configuration */
        $configuration = $this->entityManager->findOneBy(
            DocumentBaseConfigDefinition::class,
            [
                'documentType.technicalName' => $documentTypeTechnicalName,
                'global' => true,
            ],
            $context,
            [
                'documentType',
                'logo',
            ],
        );

        $documentConfiguration = DocumentConfigurationFactory::createConfiguration([], $configuration);

        return $this->hydrateCompanyCountry($documentConfiguration, $languageId, $context);
    }

    private function hydrateCompanyCountry(
        DocumentConfiguration $documentConfiguration,
        string $languageId,
        Context $context,
    ): DocumentConfiguration {
        if (!Uuid::isValid($documentConfiguration->getCompanyCountryId())) {
            return $documentConfiguration;
        }

        $localizedContext = $this->contextFactory->createLocalizedContext($languageId, $context);
        /** @var CountryEntity|null $country */
        $country = $this->entityManager->findByPrimaryKey(
            CountryDefinition::class,
            $documentConfiguration->getCompanyCountryId(),
            $localizedContext,
        );
        if ($country) {
            $documentConfiguration->setCompanyCountry($country);
        }

        return $documentConfiguration;
    }
}
