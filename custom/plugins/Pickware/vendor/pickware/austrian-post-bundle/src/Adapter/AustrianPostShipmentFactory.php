<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\AustrianPostBundle\Adapter;

use Pickware\AustrianPostBundle\Api\AustrianPostProduct;
use Pickware\AustrianPostBundle\Api\AustrianPostShipment;
use Pickware\AustrianPostBundle\Config\AustrianPostConfig;
use Pickware\ShippingBundle\Shipment\ShipmentBlueprint;

class AustrianPostShipmentFactory
{
    public function createAustrianPostShipmentFromShipmentBlueprint(
        ShipmentBlueprint $shipmentBlueprint,
        AustrianPostConfig $austrianPostConfig,
    ): AustrianPostShipment {
        $austrianPostShipmentConfig = new AustrianPostShipmentConfig($shipmentBlueprint->getShipmentConfig());

        return new AustrianPostShipment(
            receiverAddress: $shipmentBlueprint->getReceiverAddress(),
            senderAddress: $shipmentBlueprint->getSenderAddress(),
            parcels: $shipmentBlueprint->getParcels(),
            product: $austrianPostShipmentConfig->getProduct(),
            shipmentServices: $austrianPostShipmentConfig->getShipmentServiceOptions(
                $austrianPostConfig,
            ),
            deliveryInstruction: $austrianPostShipmentConfig->getDeliveryInstruction(),
            customerReference: $shipmentBlueprint->getCustomerReference(),
            invoiceNumber: $shipmentBlueprint->getInvoiceNumber(),
            typeOfShipment: $shipmentBlueprint->getTypeOfShipment(),
            createExportDeclaration: $austrianPostShipmentConfig->shouldCreateExportDocuments(),
            movementReferenceNumber: $shipmentBlueprint->getMovementReferenceNumber(),
        );
    }

    public function createAustrianPostReturnShipmentFromShipmentBlueprint(
        ShipmentBlueprint $shipmentBlueprint,
    ): AustrianPostShipment {
        $austrianPostShipmentConfig = new AustrianPostShipmentConfig($shipmentBlueprint->getShipmentConfig());

        if ($shipmentBlueprint->getSenderAddress()->getCountry() === null) {
            throw AustrianPostAdapterException::senderCountryMissing();
        }

        return new AustrianPostShipment(
            receiverAddress: $shipmentBlueprint->getReceiverAddress(),
            senderAddress: $shipmentBlueprint->getSenderAddress(),
            parcels: $shipmentBlueprint->getParcels(),
            product: AustrianPostProduct::getProductForReturnShipmentFromCountry(
                $shipmentBlueprint->getSenderAddress()->getCountry(),
            ),
            customerReference: $shipmentBlueprint->getCustomerReference(),
            invoiceNumber: $shipmentBlueprint->getInvoiceNumber(),
            typeOfShipment: $shipmentBlueprint->getTypeOfShipment(),
            createExportDeclaration: $austrianPostShipmentConfig->shouldCreateExportDocuments(),
        );
    }
}
