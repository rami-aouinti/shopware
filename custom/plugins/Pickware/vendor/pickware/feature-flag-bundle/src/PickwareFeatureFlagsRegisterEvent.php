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
 * @deprecated Should not be used as feature flags are automatically registered by the FeatureFlagBundle. Will be removed in 4.0.0.
 */
#[Exclude]
class PickwareFeatureFlagsRegisterEvent
{
    public function __construct(private readonly FeatureFlagCollection $featureFlags) {}

    public function addFeatureFlag(FeatureFlag|string $featureFlag, ?bool $enabled = null): void
    {
        trigger_error('The ability to register feature flags with the PickwareFeatureFlagsRegisterEvent will be removed in 4.0.0.', E_USER_DEPRECATED);
        if (is_string($featureFlag)) {
            trigger_error('Passing the name of a feature flag as string will be removed in 4.0.0.', E_USER_DEPRECATED);
            $name = $featureFlag;
            if ($enabled === null) {
                throw new InvalidArgumentException(
                    'When passing the name of a feature flag, you must also pass whether it is enabled or not.',
                );
            }
            $featureFlag = new ProductionFeatureFlag($name, $enabled);
        } elseif ($enabled !== null) {
            throw new InvalidArgumentException(
                'When passing a FeatureFlag instance, you must not pass whether it is enabled or not.',
            );
        }

        // We changed the registration from event to autowiring. This event class exists for backwards compatibility.
        // Because of this backwards compatibility it is now possible that a plugin "registers" their feature flag two
        // times. We have to ignore this case here.
        // (Duplicate feature flag registration will be an error again when this deprecated class is removed.)
        $alreadyRegisteredFeatureFlag = $this->featureFlags->getByName($featureFlag->getName());

        if ($alreadyRegisteredFeatureFlag) {
            trigger_error('Feature flag is already registered and should not be registered via the PickwareFeatureFlagsRegisterEvent again.', E_USER_DEPRECATED);

            return;
        }

        $this->featureFlags->add($featureFlag);
    }
}
