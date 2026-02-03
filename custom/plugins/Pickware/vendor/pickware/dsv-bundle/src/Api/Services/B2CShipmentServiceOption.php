<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Api\Services;

class B2CShipmentServiceOption extends AbstractShipmentServiceOption
{
    public function applyToShipmentArray(array &$shipmentArray): void
    {
        $shipmentArray['services']['b2C'] = [
            'email' => $shipmentArray['parties']['receiver']['contact']['email'] ?? null,
            'phone' => $shipmentArray['parties']['receiver']['contact']['telephone'] ?? null,
        ];
    }
}
