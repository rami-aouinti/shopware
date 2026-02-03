<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\Values;

use JsonSerializable;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class CostCenters implements JsonSerializable
{
    public function __construct(
        private readonly ?string $salesChannelCostCenter,
        private readonly bool $switchCostCentersOrder,
    ) {}

    public function jsonSerialize(): mixed
    {
        return [
            'salesChannelCostCenter' => $this->getSalesChannelCostCenter(),
            'switchCostCentersOrder' => $this->getSwitchCostCentersOrder(),
        ];
    }

    public static function fromArray(array $array): self
    {
        return new self(
            salesChannelCostCenter: $array['salesChannelCostCenter'] ?? null,
            switchCostCentersOrder: $array['switchCostCentersOrder'] ?? false,
        );
    }

    public function getSalesChannelCostCenter(): ?string
    {
        return $this->salesChannelCostCenter;
    }

    public function getSwitchCostCentersOrder(): bool
    {
        return $this->switchCostCentersOrder;
    }
}
