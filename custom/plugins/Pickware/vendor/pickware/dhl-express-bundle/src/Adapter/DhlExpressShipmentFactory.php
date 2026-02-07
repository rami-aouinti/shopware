<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Adapter;

use Pickware\DhlExpressBundle\Api\DhlExpressShipment;
use Pickware\DhlExpressBundle\Api\Services\ShipmentNotificationOption;
use Pickware\DhlExpressBundle\Api\Services\ShipmentServiceOption;
use Pickware\DhlExpressBundle\Config\DhlExpressConfig;
use Pickware\ShippingBundle\Shipment\FeeType;
use Pickware\ShippingBundle\Shipment\ShipmentBlueprint;

class DhlExpressShipmentFactory
{
    public function createDhlExpressShipmentFromShipmentBlueprint(
        ShipmentBlueprint $shipmentBlueprint,
        DhlExpressConfig $dhlExpressConfig,
    ): DhlExpressShipment {
        if (count($shipmentBlueprint->getParcels()) === 0) {
            throw DhlExpressAdapterException::shipmentBlueprintHasNoParcels();
        }

        $shipmentConfig = new DhlExpressShipmentConfig($shipmentBlueprint->getShipmentConfig());

        $dhlExpressShipment = new DhlExpressShipment(
            senderAddress: $shipmentBlueprint->getSenderAddress(),
            receiverAddress: $shipmentBlueprint->getReceiverAddress(),
            parcels: $shipmentBlueprint->getParcels(),
            product: $shipmentConfig->getProduct(),
            shipperNumber: $dhlExpressConfig->getShipperNumber(),
            incoterm: $shipmentConfig->getIncoterm(),
            totalShipmentValue: $shipmentBlueprint->getTotalValue(),
            typeOfShipment: $shipmentBlueprint->getTypeOfShipment(),
            invoiceNumber: $shipmentBlueprint->getInvoiceNumber(),
            invoiceDate: $shipmentBlueprint->getInvoiceDate(),
            officeOfOrigin: $shipmentBlueprint->getOfficeOfOrigin(),
            shippingCost: $shipmentBlueprint->getTotalFeesOfType(FeeType::ShippingCosts),
            items: $shipmentBlueprint->getItemsOfAllParcelsGroupByAttributes(),
            movementReferenceNumber: $shipmentBlueprint->getMovementReferenceNumber(),
            shipmentDescription: $shipmentBlueprint->getExplanationIfTypeOfShipmentIsOther(),
            importerOfRecordsAddress: $shipmentBlueprint->getImporterOfRecordsAddress(),
        );

        $services = $this->getShipmentServices(
            $dhlExpressShipment,
            $shipmentConfig,
        );

        if ($dhlExpressConfig->isDispatchNotificationEnabled()) {
            $services[] = ShipmentNotificationOption::dispatchNotificationOption(
                $dhlExpressShipment->getReceiverAddress()->getEmail(),
                $dhlExpressShipment->getReceiverAddress()->getCountryIso(),
            );
        }
        $dhlExpressShipment->setShipmentServices($services);

        return $dhlExpressShipment;
    }

    public function createDhlExpressReturnShipmentFromShipmentBlueprint(
        ShipmentBlueprint $shipmentBlueprint,
        DhlExpressConfig $dhlExpressConfig,
    ): DhlExpressShipment {
        if (count($shipmentBlueprint->getParcels()) === 0) {
            throw DhlExpressAdapterException::shipmentBlueprintHasNoParcels();
        }

        $shipmentConfig = new DhlExpressShipmentConfig($shipmentBlueprint->getShipmentConfig());

        $dhlExpressShipment = new DhlExpressShipment(
            senderAddress: $shipmentBlueprint->getSenderAddress(),
            receiverAddress: $shipmentBlueprint->getReceiverAddress(),
            parcels: $shipmentBlueprint->getParcels(),
            product: $shipmentConfig->getProduct(),
            shipperNumber: $dhlExpressConfig->getShipperNumber(),
            incoterm: $shipmentConfig->getIncoterm(),
            totalShipmentValue: $shipmentBlueprint->getTotalValue(),
            typeOfShipment: $shipmentBlueprint->getTypeOfShipment(),
            invoiceNumber: $shipmentBlueprint->getInvoiceNumber(),
            invoiceDate: $shipmentBlueprint->getInvoiceDate(),
            officeOfOrigin: $shipmentBlueprint->getOfficeOfOrigin(),
            shippingCost: $shipmentBlueprint->getTotalFeesOfType(FeeType::ShippingCosts),
            items: $shipmentBlueprint->getItemsOfAllParcelsGroupByAttributes(),
            shipmentDescription: $shipmentBlueprint->getExplanationIfTypeOfShipmentIsOther(),
        );

        $services = $this->getShipmentServices(
            $dhlExpressShipment,
            $shipmentConfig,
        );

        $services[] = ShipmentServiceOption::returnService();
        $dhlExpressShipment->setShipmentServices($services);

        return $dhlExpressShipment;
    }

    private function getShipmentServices(
        DhlExpressShipment $dhlExpressShipment,
        DhlExpressShipmentConfig $shipmentConfig,
    ): array {
        $services = $shipmentConfig->getShipmentServiceOptions();
        if ($shipmentConfig->getProduct()->isCustomsDeclarable()) {
            $dhlExpressShipment->enableExportDocuments();
            $services[] = ShipmentServiceOption::paperlessTrade();
        }

        return $services;
    }
}
