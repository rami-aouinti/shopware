<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\FeatureFlagBundle;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PickwareFeatureFlagsFilterEvent
{
    /**
     * Priority for subscribers that activate/deactivate a feature flag for on-premises installations.
     */
    public const PRIORITY_ON_PREMISES = 0;

    /**
     * Priority for subscribers that activate/deactivate a feature flag because of a cloud feature plan.
     */
    public const PRIORITY_CLOUD_FEATURE_PLANS = -1000;

    /**
     * Priority for subscribers that activate/deactivate a feature flag because of an on-premises feature plan.
     */
    public const PRIORITY_ON_PREMISES_FEATURE_PLANS = -1500;

    /**
     * Priority for subscribers that activate/deactivate a feature flag because of manual overrides by the user, e.g.
     * the feature flag plugin.
     */
    public const PRIORITY_MANUAL_OVERRIDES = -2000;

    /**
     * Priority for subscribers that activate/deactivate a feature flag for tests.
     */
    public const PRIORITY_TESTS = -3000;

    public function __construct(private readonly FeatureFlagCollection $featureFlags) {}

    public function getFeatureFlags(): FeatureFlagCollection
    {
        return $this->featureFlags;
    }

    /**
     * @deprecated Will be removed in 4.0. Get the feature flag via getFeatureFlags()->getByName($name) and call
     *     enable() on it.
     */
    public function enable(string $name): void
    {
        $this->featureFlags->getByName($name)?->enable();
    }

    /**
     * @deprecated Will be removed in 4.0. Get the feature flag via getFeatureFlags()->getByName($name) and call
     *     disable() on it.
     */
    public function disable(string $name): void
    {
        $this->featureFlags->getByName($name)?->disable();
    }
}
