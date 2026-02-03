<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\IncompatibilityBundle\Incompatibility;

use InvalidArgumentException;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

class IncompatibilityService
{
    private const DEFAULT_LOCALE = 'de-DE';

    // All our translatable warnings should contain a German and English translation
    private const REQUIRED_TRANSLATION_LOCALES = [
        self::DEFAULT_LOCALE,
        'en-GB',
    ];

    public function __construct(
        #[TaggedLocator('pickware_incompatibility_bundle.incompatibility_verifier')]
        private readonly ServiceLocator $serviceLocator,
        #[TaggedIterator('pickware_incompatibility_bundle.incompatibility_provider')]
        private readonly iterable $incompatibilityProviders,
        private readonly AppLocaleProvider $appLocaleProvider,
    ) {
        // validate providers, verifiers, and translated warnings for all provided incompatibilities
        foreach ($this->incompatibilityProviders as $incompatibilityProvider) {
            if (!($incompatibilityProvider instanceof IncompatibilityProvider)) {
                throw new InvalidArgumentException(sprintf(
                    "Service %s tagged with 'pickware_incompatibility_bundle.incompatibility_provider' needs to implement the %s interface.",
                    $incompatibilityProvider::class,
                    IncompatibilityProvider::class,
                ));
            }
            foreach ($incompatibilityProvider->getIncompatibilities() as $incompatibility) {
                if (!$this->serviceLocator->has($incompatibility->getVerifierServiceName())) {
                    throw new InvalidArgumentException(sprintf(
                        'Cannot find service %s using the service locator, did you forget to implement %s?',
                        $incompatibility->getVerifierServiceName(),
                        IncompatibilityVerifier::class,
                    ));
                }

                $existingLocales = array_keys($incompatibility->getTranslatedWarnings());
                if (count(array_diff(self::REQUIRED_TRANSLATION_LOCALES, $existingLocales)) !== 0) {
                    throw new InvalidArgumentException(sprintf(
                        'You have to provide translations for at least the following locales: %s.',
                        implode(', ', self::REQUIRED_TRANSLATION_LOCALES),
                    ));
                }
            }
        }
    }

    public function getApplicableIncompatibilityWarnings(Context $context): array
    {
        // collect all possible incompatibilities using the same verifier
        $incompatibilityVerifierMap = [];
        foreach ($this->incompatibilityProviders as /** @var IncompatibilityProvider $incompatibilityProvider */ $incompatibilityProvider) {
            foreach ($incompatibilityProvider->getIncompatibilities() as $incompatibility) {
                $verifierName = $incompatibility->getVerifierServiceName();
                if (!array_key_exists($verifierName, $incompatibilityVerifierMap)) {
                    $incompatibilityVerifierMap[$verifierName] = [];
                }
                $incompatibilityVerifierMap[$verifierName][] = $incompatibility;
            }
        }

        $locale = $this->appLocaleProvider->getLocaleFromContext($context);
        $warnings = [];
        foreach ($incompatibilityVerifierMap as $verifierServiceName => $incompatibilities) {
            /** @var IncompatibilityVerifier $verifier */
            $verifier = $this->serviceLocator->get($verifierServiceName);
            foreach ($verifier->verifyIncompatibilities($incompatibilities, $context) as $applicableIncompatibility) {
                $translatedWarnings = $applicableIncompatibility->getTranslatedWarnings();
                if (array_key_exists($locale, $translatedWarnings)) {
                    $warnings[] = $translatedWarnings[$locale];
                } else {
                    $warnings[] = $translatedWarnings[self::DEFAULT_LOCALE];
                }
            }
        }

        return $warnings;
    }
}
