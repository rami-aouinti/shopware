<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\Subscriber;

use Pickware\FeatureFlagBundle\FeatureFlag;
use Pickware\FeatureFlagBundle\FeatureFlagType;
use Pickware\FeatureFlagBundle\PickwareFeatureFlagsFilterEvent;
use Pickware\LicenseBundle\Model\PluginInstallationRepository;
use Pickware\LicenseBundle\PickwareAccount\PickwareAccountService;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FeatureFlagOverrider implements EventSubscriberInterface
{
    private const NON_DEACTIVATABLE_FEATURE_FLAG_NAMES = [
        'pickware-deutsche-post.feature.deutsche-post',
        'pickware-dhl.feature.dhl',
        'pickware-gls.feature.gls',
        'pickware-shipping-bundle.feature.data-transfer-ask-customer-policy',
        'pickware-shipping-bundle.prod.importer-of-records-address',
        'pickware-shipping-bundle.feature.shipping-bundle',
    ];

    public function __construct(
        private readonly PluginInstallationRepository $pluginInstallationRepository,
        private readonly PickwareAccountService $pickwareAccountService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            PickwareFeatureFlagsFilterEvent::class => [
                'modifyFeatureFlags',
                PickwareFeatureFlagsFilterEvent::PRIORITY_ON_PREMISES_FEATURE_PLANS,
            ],
        ];
    }

    public function modifyFeatureFlags(PickwareFeatureFlagsFilterEvent $event): void
    {
        $featureFlagsToDeactivate = ImmutableCollection::create($event->getFeatureFlags()->getItems())
            ->filter(
                fn(FeatureFlag $featureFlag) => !in_array($featureFlag->getName(), self::NON_DEACTIVATABLE_FEATURE_FLAG_NAMES),
            );
        /** @var FeatureFlag $featureFlag */
        foreach ($featureFlagsToDeactivate as $featureFlag) {
            if ($featureFlag->getType() === FeatureFlagType::Production) {
                $featureFlag->setIsActive(false);
            }
        }

        $context = Context::createDefaultContext();
        if (!$this->pickwareAccountService->isPickwareAccountConnected($context)) {
            return;
        }

        $this->pickwareAccountService->ensureUpToDatePickwareLicenseLease($context);

        $pluginInstallation = $this->pluginInstallationRepository->getPluginInstallation($context);
        $pickwareLicenseLease = $pluginInstallation->getPickwareLicenseLease();
        if ($pickwareLicenseLease === null) {
            return;
        }

        foreach ($pickwareLicenseLease->getFeatureFlags() as $featureFlagName => $isActive) {
            if (in_array($featureFlagName, $featureFlagsToDeactivate->asArray(), true) || $isActive) {
                $event->getFeatureFlags()->getByName($featureFlagName)?->setIsActive($isActive);
            }
        }
    }
}
