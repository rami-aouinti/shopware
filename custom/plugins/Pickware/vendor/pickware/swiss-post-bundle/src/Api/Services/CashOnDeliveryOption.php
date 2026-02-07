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

class CashOnDeliveryOption extends AbstractShipmentOption
{
    public function __construct(
        private readonly string $codAmount,
    ) {}

    public function applyToShipmentArray(array &$shipmentArray): void
    {
        $shipmentArray['item']['attributes']['przl'][] = 'BLN';

        $shipmentArray['item']['additionalData'][] = [
            'type' => 'NN_BETRAG',
            'value' => $this->codAmount,
        ];
    }
}
