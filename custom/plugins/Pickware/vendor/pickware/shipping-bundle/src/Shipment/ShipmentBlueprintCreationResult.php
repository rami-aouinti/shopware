<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment;

use JsonSerializable;
use Pickware\ShippingBundle\Privacy\RemovedFieldTree;

class ShipmentBlueprintCreationResult implements JsonSerializable
{
    public function __construct(
        public readonly string $orderId,
        public readonly ShipmentBlueprint $shipmentBlueprint,
        public readonly string $status = 'success',
        public readonly array $errors = [],
        public readonly ?RemovedFieldTree $removedFields = null,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'orderId' => $this->orderId,
            'shipmentBlueprint' => $this->shipmentBlueprint,
            'errors' => $this->errors,
            'removedFields' => $this->removedFields,
        ];
    }
}
