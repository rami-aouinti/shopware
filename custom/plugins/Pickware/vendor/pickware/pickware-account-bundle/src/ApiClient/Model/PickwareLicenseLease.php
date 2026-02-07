<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareAccountBundle\ApiClient\Model;

use DateTimeImmutable;
use JsonSerializable;
use function Pickware\PhpStandardLibrary\Optional\doIf;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * When retrieving the current license information from the Pickware Account, the Business Platform provides a
 * short-lived lease of the license instead of the full license details. This lease includes:
 *   - The active feature flags for the plugin
 *   - The expiration date of the lease, indicating how long the feature flags can be used
 * The lease's validity period is typically much shorter than the actual license duration. It is designed to be
 * refreshed frequently, ensuring the plugin always operates with feature flags that reflect the current license state.
 */
#[Exclude]
readonly class PickwareLicenseLease implements JsonSerializable
{
    /**
     * @param array<string, bool> $featureFlags
     */
    public function __construct(
        private DateTimeImmutable $validUntil,
        private array $featureFlags,
        private string $planType,
        private string $planVersion,
        private string $subscriptionState,
        private ?DateTimeImmutable $subscriptionExpiresAfter,
    ) {}

    public static function fromArray(array $array): self
    {
        return new self(
            validUntil: new DateTimeImmutable($array['validUntil']),
            featureFlags: $array['featureFlags'],
            planType: $array['planType'],
            planVersion: $array['planVersion'],
            subscriptionState: $array['subscriptionState'],
            subscriptionExpiresAfter: doIf(
                $array['subscriptionExpiresAfter'],
                fn($value) => new DateTimeImmutable($value),
            ),
        );
    }

    public function getValidUntil(): DateTimeImmutable
    {
        return $this->validUntil;
    }

    /**
     * @return array<string, bool>
     */
    public function getFeatureFlags(): array
    {
        return $this->featureFlags;
    }

    public function getPlanType(): string
    {
        return $this->planType;
    }

    public function getPlanVersion(): string
    {
        return $this->planVersion;
    }

    public function getSubscriptionState(): string
    {
        return $this->subscriptionState;
    }

    public function getSubscriptionExpiresAfter(): ?DateTimeImmutable
    {
        return $this->subscriptionExpiresAfter;
    }

    public function jsonSerialize(): array
    {
        return [
            'validUntil' => $this->validUntil->format(DATE_ATOM),
            'featureFlags' => $this->featureFlags,
            'planType' => $this->planType,
            'planVersion' => $this->planVersion,
            'subscriptionState' => $this->subscriptionState,
            'subscriptionExpiresAfter' => $this->subscriptionExpiresAfter?->format(DATE_ATOM),
        ];
    }
}
