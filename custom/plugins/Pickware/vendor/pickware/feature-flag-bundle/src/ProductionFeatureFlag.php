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

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Production feature flags exist to enable and disable a feature dynamically in production.
 *
 * This can be for example because an additionally paid plugin will enable a feature in a free plugin.
 *
 * A production feature flag should only be used if the feature is enabled or disabled by another bundle, plugin, or
 * even external service, like the Pickware Cloud.
 *
 * Please pay attention that your code has to be developed in a way such that it can handle the feature being enabled
 * or disabled at any time. A dev feature flag does not fulfill this constraint because it may only hide the initial
 * entry point for a feature in the UI. When such a feature was enabled once it can lead to data in the database that
 * will make the code to act like the feature is active.
 *
 * A dev feature flag must not be converted into a production feature flag without changing the name of the feature
 * flag. This is necessary to ensure that the feature flag is not accidentally enabled in production when a plugin is
 * installed in a version where the feature was not yet ready.
 *
 * Adding a production feature flag to a feature that was previously hidden behind a dev feature flag has to be
 * considered carefully. Because of the reasons mentioned above, a development feature flag cannot be easily converted
 * into a production feature flag. You have to create a new production feature flag and reconsider which code has to be
 * hidden behind the feature flag and whether the data model supports such a feature toggle.
 */
#[Exclude]
class ProductionFeatureFlag extends FeatureFlag
{
    /**
     * @param string $name The name of the feature flag. The name must contain ".prod."
     * @param bool $isActiveOnPremises Whether the feature is active on premises or has to be enabled by another bundle
     *     or plugin.
     */
    public function __construct(string $name, bool $isActiveOnPremises)
    {
        if (!str_contains($name, '.prod.') && !str_contains($name, '.feature.')) {
            // .feature. is only allowed for compatibility reasons
            throw new InvalidArgumentException(
                'The name of a production feature flag must contain ".prod.".',
            );
        }
        if (str_contains($name, '.dev.')) {
            throw new InvalidArgumentException('The name of a production feature flag must not contain ".dev.".');
        }

        parent::__construct(name: $name, isActive: $isActiveOnPremises, type: FeatureFlagType::Production);
    }
}
