<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Api\Services;

class ShipmentRatingOption extends AbstractShipmentService
{
    public function __construct(private readonly string $serviceName) {}

    public static function negotiatedRates(): self
    {
        return new self('NegotiatedRatesIndicator');
    }

    public function applyToShipmentArray(array &$shipmentArray): void
    {
        $shipment = &$shipmentArray['ShipmentRequest']['Shipment'];

        if (!isset($shipment['ShipmentRatingOptions'])) {
            $shipment['ShipmentRatingOptions'] = [];
        }

        $shipment['ShipmentRatingOptions'][$this->serviceName] = '';
    }
}
