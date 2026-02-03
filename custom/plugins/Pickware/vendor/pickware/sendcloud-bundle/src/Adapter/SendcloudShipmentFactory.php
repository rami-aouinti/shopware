<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SendcloudBundle\Adapter;

use LogicException;
use Pickware\SendcloudBundle\Api\SendcloudShipment;
use Pickware\ShippingBundle\Shipment\ShipmentBlueprint;

class SendcloudShipmentFactory
{
    public function createSendcloudShipmentFromShipmentBlueprint(
        ShipmentBlueprint $shipmentBlueprint,
    ): SendcloudShipment {
        if (count($shipmentBlueprint->getParcels()) === 0) {
            throw new LogicException('Shipment has no parcels.');
        }

        if (
            !array_key_exists('shippingMethodTechnicalName', $shipmentBlueprint->getShipmentConfig())
            || $shipmentBlueprint->getShipmentConfig()['shippingMethodTechnicalName'] === null
        ) {
            throw SendcloudAdapterException::noCheckoutDeliveryMethodNameSet();
        }

        return new SendcloudShipment(
            receiverAddress: $shipmentBlueprint->getReceiverAddress(),
            parcels: $shipmentBlueprint->getParcels(),
            shippingMethodTechnicalName: $shipmentBlueprint->getShipmentConfig()['shippingMethodTechnicalName'],
            typeOfShipment: $shipmentBlueprint->getTypeOfShipment(),
            invoiceNumber: $shipmentBlueprint->getInvoiceNumber(),
            invoiceDate: $shipmentBlueprint->getInvoiceDate(),
            importerOfRecordsAddress: $shipmentBlueprint->getImporterOfRecordsAddress(),
            sendExportInformation: $shipmentBlueprint->getShipmentConfig()['sendExportInformation'],
        );
    }
}
