<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SwissPostBundle\Api\Services;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class ShipmentServiceOption extends AbstractShipmentOption
{
    private function __construct(
        private readonly string $serviceCode,
    ) {}

    public static function saturdayDelivery(): self
    {
        return new self('SA');
    }

    public static function signature(): self
    {
        return new self('SI');
    }

    public function applyToShipmentArray(array &$shipmentArray): void
    {
        $shipmentArray['item']['attributes']['przl'][] = $this->serviceCode;
    }
}
