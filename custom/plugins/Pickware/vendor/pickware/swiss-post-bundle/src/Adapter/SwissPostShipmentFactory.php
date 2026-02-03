<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SwissPostBundle\Adapter;

use Pickware\ShippingBundle\Shipment\ShipmentBlueprint;
use Pickware\SwissPostBundle\Api\SwissPostShipment;
use Pickware\SwissPostBundle\Config\SwissPostConfig;

class SwissPostShipmentFactory
{
    private const POST_FRANKING_LICENSE_PRODUCT_CODES = [
        'APLUS',
        'APOST',
        'BPOST',
    ];
    private const REGISTERED_INTERNATIONAL_FRANKING_LICENSE_PRODUCT_CODES = [
        'RETR',
    ];

    public function createSwissPostShipmentsFromShipmentBlueprint(
        ShipmentBlueprint $shipmentBlueprint,
        string $shipmentId,
        SwissPostConfig $swissPostConfig,
    ): array {
        if (count($shipmentBlueprint->getParcels()) === 0) {
            throw SwissPostAdapterException::shipmentBlueprintHasNoParcels();
        }

        $receiverAddress = $shipmentBlueprint->getReceiverAddress();

        $swissPostShipments = [];
        $swissPostShipmentConfig = new SwissPostShipmentConfig($shipmentBlueprint->getShipmentConfig());
        foreach ($shipmentBlueprint->getParcels() as $parcelIndex => $parcel) {
            if ($parcel->getTotalWeight() === null) {
                throw SwissPostAdapterException::parcelTotalWeightIsUndefined($parcelIndex + 1);
            }

            if (count(array_intersect($swissPostShipmentConfig->getProductCodes(), self::POST_FRANKING_LICENSE_PRODUCT_CODES)) > 0) {
                $frankingLicense = $swissPostConfig->getPostFrankingLicense();
            } elseif (count(array_intersect($swissPostShipmentConfig->getProductCodes(), self::REGISTERED_INTERNATIONAL_FRANKING_LICENSE_PRODUCT_CODES)) > 0) {
                $frankingLicense = $swissPostConfig->getRegisteredIntlFrankingLicense();
            } else {
                $frankingLicense = $swissPostConfig->getFrankingLicense();
            }

            $swissPostShipment = new SwissPostShipment(
                $shipmentBlueprint->getSenderAddress(),
                $receiverAddress,
                $parcel,
                (new ParcelReference($shipmentId, $parcelIndex))->toString(),
                $swissPostShipmentConfig->getProductCodes(),
                $frankingLicense,
                $swissPostConfig->getDomicilePostOffice(),
                count($shipmentBlueprint->getParcels()),
                $swissPostConfig->useTestingWebservice(),
            );
            $swissPostShipment->setShipmentServiceOptions($swissPostShipmentConfig->getShipmentServiceOptions($shipmentBlueprint->getReceiverAddress()));

            $swissPostShipments[] = $swissPostShipment;
        }

        return $swissPostShipments;
    }

    public function createSwissPostReturnShipmentsFromShipmentBlueprint(
        ShipmentBlueprint $shipmentBlueprint,
        string $shipmentId,
        SwissPostConfig $swissPostConfig,
    ): array {
        if (count($shipmentBlueprint->getParcels()) === 0) {
            throw SwissPostAdapterException::shipmentBlueprintHasNoParcels();
        }

        $swissPostShipments = [];
        foreach ($shipmentBlueprint->getParcels() as $parcelIndex => $parcel) {
            if ($parcel->getTotalWeight() === null) {
                throw SwissPostAdapterException::parcelTotalWeightIsUndefined($parcelIndex + 1);
            }

            $swissPostShipments[] = new SwissPostShipment(
                $shipmentBlueprint->getSenderAddress(),
                $shipmentBlueprint->getReceiverAddress(),
                $parcel,
                (new ParcelReference($shipmentId, $parcelIndex))->toString(),
                [
                    'GAS',
                    'ECO',
                ],
                $swissPostConfig->getFrankingLicense(),
                $swissPostConfig->getDomicilePostOffice(),
                count($shipmentBlueprint->getParcels()),
                $swissPostConfig->useTestingWebservice(),
            );
        }

        return $swissPostShipments;
    }
}
