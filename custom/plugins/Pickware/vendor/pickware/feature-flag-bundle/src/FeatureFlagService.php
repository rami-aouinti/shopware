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

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class FeatureFlagService implements ResettableInterface
{
    private ?FeatureFlagCollection $featureFlags = null;

    public function __construct(
        #[TaggedIterator('pickware_feature_flag.feature_flag')]
        private iterable $registeredFeatureFlags,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function reset(): void
    {
        $this->featureFlags = null;
    }

    public function getFeatureFlags(): FeatureFlagCollection
    {
        if (!$this->featureFlags) {
            $this->featureFlags = new FeatureFlagCollection();
            $this->featureFlags->add(...$this->registeredFeatureFlags);

            // Will be removed in version 4.0.0
            $this->eventDispatcher->dispatch(new PickwareFeatureFlagsRegisterEvent($this->featureFlags));

            $this->eventDispatcher->dispatch(new PickwareFeatureFlagsFilterEvent($this->featureFlags));
        }

        return $this->featureFlags;
    }

    public function isActive(string $name): bool
    {
        $featureFlag = $this->getFeatureFlags()->getByName($name);

        return $featureFlag?->isActive() ?? false;
    }
}
