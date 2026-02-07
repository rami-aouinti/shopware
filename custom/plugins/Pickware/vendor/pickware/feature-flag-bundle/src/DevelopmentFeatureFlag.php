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
 * Development feature flags only exist to hide a feature in production during development.
 *
 * They can be safely removed after the feature has been released.
 *
 * A development feature flag is not designed to enable or disable a feature in production at any time, because it may
 * not hide the feature completely, or will lead to code execution that is not compatible with the database anymore.
 * Once a development feature flag is released (activated), it cannot be safely disabled anymore.
 *
 * A dev feature flag must not be converted into a production feature flag without changing the name of the feature
 * flag. This is necessary to ensure that the feature flag is not accidentally enabled in production, when a plugin is
 * installed in a version where the feature was not yet ready.
 *
 * Adding a production feature flag to a feature that was previously hidden behind a dev feature flag has to be
 * considered carefully. Because of the reasons mentioned above, a development feature flag cannot be easily converted
 * into a production feature flag. You have to create a new production feature flag and reconsider which code has to be
 * hidden behind the feature flag and whether the data model supports such a feature toggle.
 */
#[Exclude]
class DevelopmentFeatureFlag extends FeatureFlag
{
    /**
     * @param bool $requiresUpdate Whether the feature requires a reinstall of the plugin to be fully activated.
     */
    public function __construct(string $name, bool $isFeatureReleased, private readonly bool $requiresUpdate = false)
    {
        if (!str_contains($name, '.dev.')) {
            throw new InvalidArgumentException('The name of a development feature flag must contain ".dev.".');
        }
        if (str_contains($name, '.prod.') || str_contains($name, '.feature.')) {
            throw new InvalidArgumentException(
                'The name of a development feature flag must not contain ".prod." or ".feature.".',
            );
        }

        parent::__construct(name: $name, isActive: $isFeatureReleased, type: FeatureFlagType::Development);
    }

    public function jsonSerialize(): array
    {
        return [
            'requiresUpdate' => $this->requiresUpdate,
            ...parent::jsonSerialize(),
        ];
    }
}
