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

class DeliveryInstructionsOption extends AbstractShipmentOption
{
    public function __construct(
        private readonly string $deliveryInstructions,
        private readonly ?string $deliveryDate = null,
    ) {}

    public function applyToShipmentArray(array &$shipmentArray): void
    {
        $shipmentArray['item']['attributes']['przl'][] = $this->deliveryInstructions;

        if ($this->deliveryDate) {
            $shipmentArray['item']['attributes']['deliveryDate'] = sprintf('%sT12:00:00Z', $this->deliveryDate);
        }
    }
}
