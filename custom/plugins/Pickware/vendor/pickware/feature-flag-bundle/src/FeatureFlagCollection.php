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

use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Traversable;

#[Exclude]
final class FeatureFlagCollection implements JsonSerializable, IteratorAggregate
{
    /** @var FeatureFlag[] */
    private array $featureFlags = [];

    public function __construct(FeatureFlag ...$featureFlags)
    {
        $this->add(...$featureFlags);
    }

    /**
     * @return Traversable<int, FeatureFlag>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->featureFlags);
    }

    public function add(FeatureFlag ...$featureFlags): void
    {
        foreach ($featureFlags as $featureFlag) {
            if ($this->getByName($featureFlag->getName())) {
                throw new InvalidArgumentException(sprintf(
                    'Feature flag "%s" was already registered. It cannot be registered again.',
                    $featureFlag->getName(),
                ));
            }
            $this->featureFlags[] = $featureFlag;
        }
    }

    public function getItems(): array
    {
        return $this->featureFlags;
    }

    public function getByName(string $name): ?FeatureFlag
    {
        foreach ($this->featureFlags as $featureFlag) {
            if ($featureFlag->getName() === $name) {
                return $featureFlag;
            }
        }

        return null;
    }

    /**
     * @return array<string, bool>
     */
    public function jsonSerialize(): array
    {
        $featureFlags = [];
        foreach ($this->featureFlags as $featureFlag) {
            $featureFlags[$featureFlag->getName()] = $featureFlag->isActive();
        }

        return $featureFlags;
    }

    public function __clone(): void
    {
        foreach ($this->featureFlags as &$value) {
            $value = clone $value;
        }
    }
}
