<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Translation;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Exception;
use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Context;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetDefinition;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * This class can be removed as soon as the Shopware min compatibility is raised to 6.5.5.0 or above. Use Shopware's
 * Translator with setLocale() and trans() directly.
 * @see Shopware\Core\Framework\Adapter\Translation\Translator
 */
class Translator
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
        private readonly EntityManager $entityManager,
        #[Autowire(service: 'Shopware\\Core\\Framework\\Adapter\\Translation\\Translator')]
        private readonly AbstractTranslator $shopwareTranslator,
    ) {}

    public function setTranslationLocale(string $localeCode, ?Context $context = null): void
    {
        if (!$this->satisfiesShopwareVersion('>=6.5.5.0')) {
            if (!$context) {
                throw new InvalidArgumentException(
                    'Second parameter `Context $context` is non-optional when used in Shopware version below 6.5.5.0.',
                );
            }
            $this->setTranslationLocaleOnTranslatorInterface($localeCode, $context);

            return;
        }

        $this->shopwareTranslator->setLocale($localeCode);
    }

    public function translate(string $snippetId, array $parameters = []): string
    {
        if (!$this->satisfiesShopwareVersion('>=6.5.5.0')) {
            // can be removed as soon as Shopware min compatibility is raised to 6.5.5.0 or above
            return $this->translator->trans($snippetId, $parameters);
        }

        return $this->shopwareTranslator->trans($snippetId, $parameters);
    }

    /**
     * @deprecated will be removed as soon as Shopware min compatibility is raised to 6.5.5.0 or above
     */
    private function setTranslationLocaleOnTranslatorInterface(string $localeCode, Context $context): void
    {
        // The Shopware Translator uses a SnippetSet to translate snippets. It expects the SnippetSetId to be present
        // on the request as an attribute. As the SnippetSetId is only set automatically for sales channel api and
        // storefront requests we need to set it manually when handling admin api requests.
        // We also need to reset the translator as another snippet set id of a different language could be stored within
        // the baseTranslator, resulting in a wrong translation.
        $this->translator->reset();
        $snippetSet = $this->getSnippetSetForLocale($localeCode, $context);
        if (!$snippetSet) {
            throw TranslationException::noSnippetSetFoundForLocale($localeCode);
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            $request = new Request();
            $this->requestStack->push($request);
        }
        $request->attributes->set(
            SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID,
            $snippetSet->getId(),
        );
    }

    private function getSnippetSetForLocale(string $localeCode, Context $context): ?SnippetSetEntity
    {
        return $this->entityManager->findBy(
            SnippetSetDefinition::class,
            ['iso' => $localeCode],
            $context,
        )->first();
    }

    private function satisfiesShopwareVersion(string $shopwareVersion): bool
    {
        $shopwareCorePackageName = 'shopware/core';
        if (InstalledVersions::isInstalled($shopwareCorePackageName)) {
            return InstalledVersions::satisfies(new VersionParser(), $shopwareCorePackageName, $shopwareVersion);
        }
        $shopwarePlatformPackageName = 'shopware/platform';
        if (InstalledVersions::isInstalled($shopwarePlatformPackageName)) {
            return InstalledVersions::satisfies(new VersionParser(), $shopwarePlatformPackageName, $shopwareVersion);
        }

        throw new Exception(sprintf(
            'Cannot determine Shopware version. Neither composer package "%s" nor " "%s" are installed.',
            $shopwareCorePackageName,
            $shopwarePlatformPackageName,
        ));
    }
}
