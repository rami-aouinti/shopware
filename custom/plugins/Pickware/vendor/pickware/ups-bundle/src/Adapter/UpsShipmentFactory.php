<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\UpsBundle\Adapter;

use Pickware\ShippingBundle\Shipment\ShipmentBlueprint;
use Pickware\UpsBundle\Api\Services\ShipmentRatingOption;
use Pickware\UpsBundle\Api\Services\ShipmentServiceOption;
use Pickware\UpsBundle\Api\UpsShipment;
use Pickware\UpsBundle\Config\UpsConfig;

class UpsShipmentFactory
{
    public function createUpsShipmentFromShipmentBlueprint(
        ShipmentBlueprint $shipmentBlueprint,
        UpsConfig $upsConfig,
    ): UpsShipment {
        return $this->createUpsShipment($shipmentBlueprint, $upsConfig, false);
    }

    public function createUpsReturnShipmentFromShipmentBlueprint(
        ShipmentBlueprint $shipmentBlueprint,
        UpsConfig $upsConfig,
    ): UpsShipment {
        return $this->createUpsShipment($shipmentBlueprint, $upsConfig, true);
    }

    private function createUpsShipment(
        ShipmentBlueprint $shipmentBlueprint,
        UpsConfig $upsConfig,
        bool $isReturnShipment,
    ): UpsShipment {
        if (count($shipmentBlueprint->getParcels()) === 0) {
            throw UpsAdapterException::shipmentBlueprintHasNoParcels();
        }

        $upsShipmentConfig = new UpsShipmentConfig($shipmentBlueprint->getShipmentConfig());

        $senderAddress = $shipmentBlueprint->getSenderAddress();
        $receiverAddress = $shipmentBlueprint->getReceiverAddress();

        $upsShipment = new UpsShipment(
            senderAddress: $shipmentBlueprint->getSenderAddress(),
            receiverAddress: $shipmentBlueprint->getReceiverAddress(),
            parcels: $shipmentBlueprint->getParcels(),
            product: $upsShipmentConfig->getProduct(),
            shipperNumber: $upsConfig->getShipperNumber(),
            packaging: $upsShipmentConfig->getPackagingType(),
            customerReference: $shipmentBlueprint->getCustomerReference(),
            movementReferenceNumber: $shipmentBlueprint->getMovementReferenceNumber(),
        );

        if ($isReturnShipment) {
            $upsShipment->enableReturnShipment();
        }

        $shipmentServiceOptions = $upsShipmentConfig->getShipmentServiceOptions();
        if ($upsConfig->isNegotiatedRatesEnabled()) {
            $shipmentServiceOptions[] = ShipmentRatingOption::negotiatedRates();
        }
        if ($upsConfig->isDispatchNotificationEnabled() && !$isReturnShipment) {
            $shipmentServiceOptions[] = ShipmentServiceOption::dispatchNotificationOption(
                $receiverAddress->getEmail(),
                $upsConfig->getCustomTextForDispatchNotifications(),
            );
        }
        if ($upsConfig->isDeliveryNotificationEnabled()) {
            $shipmentServiceOptions[] = ShipmentServiceOption::deliveryNotificationOption(
                $isReturnShipment ? $senderAddress->getEmail() : $receiverAddress->getEmail(),
            );
        }
        if ($upsShipmentConfig->shouldCreateCommercialInvoice()) {
            $incoterm = $upsShipmentConfig->getIncoterm();
            if ($incoterm === null) {
                throw UpsAdapterException::incotermIsRequiredForCommercialInvoice();
            }
            $shipmentServiceOptions[] = ShipmentServiceOption::internationalForms(
                $shipmentBlueprint,
                $incoterm,
            );
        }

        $upsShipment->setShipmentServices($shipmentServiceOptions);
        $upsShipment->setPackageServices($upsShipmentConfig->getPackageServiceOptions());

        return $upsShipment;
    }
}
