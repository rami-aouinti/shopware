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

use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class FeatureFlag implements JsonSerializable
{
    private readonly FeatureFlagType $type;

    /**
     * @deprecated This constructor should not be used anymore. Use ProductionFeatureFlag or DevelopmentFeatureFlag instead.
     * Initializing this class directly will throw an error in 4.0.0.
     */
    public function __construct(
        private readonly string $name,
        private bool $isActive,
        ?FeatureFlagType $type = null,
    ) {
        if ($this::class === self::class) {
            trigger_error(
                'The FeatureFlag class should not be used directly anymore. Use ProductionFeatureFlag or DevelopmentFeatureFlag instead.',
                E_USER_DEPRECATED,
            );
        }

        if (!$type) {
            trigger_error('The type of a feature flag will be required explicitly in 3.0.0.', E_USER_DEPRECATED);
            $type = FeatureFlagType::Production;
        }
        $this->type = $type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function enable(): void
    {
        $this->isActive = true;
    }

    public function disable(): void
    {
        $this->isActive = false;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getType(): FeatureFlagType
    {
        return $this->type;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'isActive' => $this->isActive,
            'type' => $this->type,
        ];
    }
}
