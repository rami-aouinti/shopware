<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DpdBundle\Adapter;

use Pickware\DpdBundle\Config\DpdConfig;
use Pickware\ShippingBundle\Parcel\Parcel;
use Pickware\ShippingBundle\Shipment\Model\ShipmentEntity;
use Pickware\ShippingBundle\Shipment\ShipmentBlueprint;

class DpdOrderFactory
{
    /**
     * @return DpdOrder[]
     */
    public function createOrdersForShipment(
        ShipmentEntity $shipmentEntity,
        DpdConfig $dpdConfig,
    ): array {
        return $this->createDpdOrdersForShipment($shipmentEntity, $dpdConfig, false);
    }

    /**
     * @return DpdOrder[]
     */
    public function createReturnOrdersForShipment(
        ShipmentEntity $shipmentEntity,
        DpdConfig $dpdConfig,
    ): array {
        return $this->createDpdOrdersForShipment($shipmentEntity, $dpdConfig, true);
    }

    /**
     * @return DpdOrder[]
     */
    private function createDpdOrdersForShipment(
        ShipmentEntity $shipmentEntity,
        DpdConfig $dpdConfig,
        bool $isReturnShipment,
    ): array {
        $this->validateShipmentForProcessing($shipmentEntity, $dpdConfig);

        $shipmentBlueprint = $shipmentEntity->getShipmentBlueprint();
        $dpdShipmentConfig = new DpdShipmentConfig($shipmentBlueprint->getShipmentConfig());

        $orders = [];
        foreach ($shipmentBlueprint->getParcels() as $parcelIndex => $parcel) {
            $orders[] = $this->createDdpOrderForParcel(
                $shipmentEntity,
                $shipmentBlueprint,
                $dpdShipmentConfig,
                $dpdConfig,
                $parcel,
                $parcelIndex,
                $isReturnShipment,
            );
        }

        return $orders;
    }

    private function validateShipmentForProcessing(
        ShipmentEntity $shipmentEntity,
        DpdConfig $dpdConfig,
    ): void {
        $shipmentBlueprint = $shipmentEntity->getShipmentBlueprint();
        if (count($shipmentBlueprint->getParcels()) === 0) {
            throw DpdAdapterException::shipmentBlueprintHasNoParcels();
        }
        if (mb_strlen($dpdConfig->getSendingDepotId()) !== 4) {
            throw DpdAdapterException::invalidSendingDepotId();
        }
    }

    private function createDdpOrderForParcel(
        ShipmentEntity $shipmentEntity,
        ShipmentBlueprint $shipmentBlueprint,
        DpdShipmentConfig $dpdShipmentConfig,
        DpdConfig $dpdConfig,
        Parcel $parcel,
        int $parcelIndex,
        bool $isReturnShipment,
    ): DpdOrder {
        if ($parcel->getTotalWeight() === null) {
            throw DpdAdapterException::parcelTotalWeightIsUndefined();
        }

        $order = new DpdOrder();
        $order->setProduct($dpdShipmentConfig->getProduct());
        $order->setParcel($parcel);
        $order->setSendingDepotId($dpdConfig->getSendingDepotId());
        $order->setIdentificationNumber(
            (new ParcelReference($shipmentEntity->getId(), $parcelIndex))->toString(),
        );
        $order->setSenderCustomerNumber($dpdConfig->getCustomerNumber());
        $order->setShipmentServiceOptions($dpdShipmentConfig->getShipmentServiceOptions($shipmentBlueprint->getReceiverAddress()));

        if ($isReturnShipment) {
            $order->setRecipientAddress($shipmentBlueprint->getSenderAddress());
            $order->setSenderAddress($shipmentBlueprint->getReceiverAddress());
            $order->setIsReturnShipment(true);
        } else {
            $order->setRecipientAddress($shipmentBlueprint->getReceiverAddress());
            $order->setSenderAddress($shipmentBlueprint->getSenderAddress());
        }

        return $order;
    }
}
