<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Adapter;

use Pickware\DsvBundle\Api\DsvShipment;
use Pickware\DsvBundle\Config\DsvConfig;
use Pickware\ShippingBundle\Shipment\ShipmentBlueprint;

class DsvShipmentFactory
{
    public function createDsvShipmentFromShipmentBlueprint(
        ShipmentBlueprint $shipmentBlueprint,
        DsvConfig $dsvConfig,
    ): DsvShipment {
        $dsvShipmentConfig = new DsvShipmentConfig($shipmentBlueprint->getShipmentConfig());

        return new DsvShipment(
            senderAddress: $shipmentBlueprint->getSenderAddress(),
            receiverAddress: $shipmentBlueprint->getReceiverAddress(),
            parcels: $shipmentBlueprint->getParcels(),
            customerNumber: $dsvConfig->getCustomerNumber(),
            product: $dsvShipmentConfig->getProduct(),
            packageType: $dsvShipmentConfig->getPackageType(),
            packageDescription: $dsvShipmentConfig->getPackageDescription(),
            incoterm: $dsvShipmentConfig->getIncoterm(),
            placeOfIncoterm: $shipmentBlueprint->getOfficeOfOrigin(),
            customerReference: $shipmentBlueprint->getCustomerReference(),
            shipmentServices: $dsvShipmentConfig->getShipmentServiceOptions(),
        );
    }
}
