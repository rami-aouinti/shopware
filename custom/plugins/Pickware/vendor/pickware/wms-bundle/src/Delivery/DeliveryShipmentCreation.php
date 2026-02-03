<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery;

use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Document\Model\DocumentEntity;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryDocumentMappingDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Pickware\PickwareWms\Delivery\Model\DeliveryParcelDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryParcelTrackingCodeDefinition;
use Pickware\PickwareWms\PickingProcess\PickingProcessException;
use Pickware\ShippingBundle\Config\ConfigException;
use Pickware\ShippingBundle\Parcel\Parcel;
use Pickware\ShippingBundle\Parcel\ParcelItem;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\Model\ShipmentEntity;
use Pickware\ShippingBundle\Shipment\Model\TrackingCodeEntity;
use Pickware\ShippingBundle\Shipment\ShipmentBlueprint;
use Pickware\ShippingBundle\Shipment\ShipmentService;
use Pickware\UnitsOfMeasurement\PhysicalQuantity\Weight;
use RuntimeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class DeliveryShipmentCreation
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ?ShipmentService $shipmentService,
    ) {}

    public function createShipmentForDelivery(
        string $deliveryId,
        string $shipmentId,
        ShipmentBlueprint $shipmentBlueprint,
        bool $isReturnShipment,
        Context $context,
    ): void {
        if (!$this->shipmentService) {
            throw PickingProcessException::noShippingCarrierInstalled();
        }

        /** @var DeliveryEntity $delivery */
        $delivery = $this->entityManager->getByPrimaryKey(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
        );

        foreach ($shipmentBlueprint->getParcels() as $parcel) {
            $this->modifyParcelItemWeightsWithWeightOverwrite($parcel);
        }

        try {
            if ($isReturnShipment) {
                $shipmentCreationResultSet = $this->shipmentService->createReturnShipmentForOrder(
                    $shipmentBlueprint,
                    $delivery->getOrderId(),
                    $context,
                    ['id' => $shipmentId],
                );
            } else {
                $shipmentCreationResultSet = $this->shipmentService->createShipmentForOrder(
                    $shipmentBlueprint,
                    $delivery->getOrderId(),
                    $context,
                    ['id' => $shipmentId],
                );
            }
        } catch (ConfigException $exception) {
            throw PickingProcessException::carrierConfigurationInvalid($exception, $deliveryId);
        }

        if (
            count($shipmentCreationResultSet->getShipmentsOperationResults()) > 1
            || count($shipmentCreationResultSet->getShipmentsOperationResults()[0]->getProcessedShipmentIds()) > 1
        ) {
            throw new RuntimeException('Shipment creation result should contain a single shipment.');
        }

        $shipmentCreationResult = $shipmentCreationResultSet->getShipmentsOperationResults()[0];
        if (!$shipmentCreationResultSet->areAllOperationResultsSuccessful()) {
            throw PickingProcessException::shipmentCreationFailed(
                $shipmentCreationResult->getErrors(),
            );
        }

        /** @var ShipmentEntity $shipment */
        $shipment = $this->entityManager->getByPrimaryKey(
            ShipmentDefinition::class,
            $shipmentId,
            $context,
            [
                'documents',
                'trackingCodes',
            ],
        );

        // Create picking process shipping document mappings
        if ($shipment->getDocuments()->count() > 0) {
            $documentMappingPayloads = array_values(array_map(
                fn(DocumentEntity $documentEntity) => [
                    'id' => Uuid::randomHex(),
                    'deliveryId' => $deliveryId,
                    'documentId' => $documentEntity->getId(),
                ],
                $shipment->getDocuments()->getElements(),
            ));
            $this->entityManager->create(
                DeliveryDocumentMappingDefinition::class,
                $documentMappingPayloads,
                $context,
            );
        }

        // Create delivery tracking codes
        if (
            !$shipment->getIsReturnShipment()
            && $shipment->getTrackingCodes()->count() > 0
        ) {
            $parcelId = Uuid::randomHex();
            $this->entityManager->create(
                DeliveryParcelDefinition::class,
                [
                    [
                        'id' => $parcelId,
                        'shipped' => false,
                        'deliveryId' => $deliveryId,
                    ],
                ],
                $context,
            );
            $trackingCodeMappings = array_values(array_map(
                fn(TrackingCodeEntity $trackingCodeEntity) => [
                    'deliveryParcelId' => $parcelId,
                    'trackingCodeId' => $trackingCodeEntity->getId(),
                ],
                $shipment->getTrackingCodes()->getElements(),
            ));
            $this->entityManager->create(
                DeliveryParcelTrackingCodeDefinition::class,
                $trackingCodeMappings,
                $context,
            );
        }
    }

    /**
     * The app does not filter the positions of an order even though the shipment is meant for a partial delivery. Only
     * the total weight is reduced and set as the weight override. Unfortunately when the total weight is lower than
     * the sum of the item weights, DHL cannot create export documents. To still be able to generate export documents in
     * such cases, we reduce the item weight of each item in a way that the sum of the items' weights is equal to the
     * weight override.
     */
    private function modifyParcelItemWeightsWithWeightOverwrite(Parcel $parcel): void
    {
        if (!$parcel->getWeightOverwrite()) {
            // In case no weight override is set for any reason, no recalculation is necessary, because the weight is
            // already calculated via the total weight.
            return;
        }
        $actualParcelWeight = $this->getActualParcelWeight($parcel);
        if (
            $actualParcelWeight !== null
            && $parcel->getWeightOverwrite()->isEqualTo($actualParcelWeight, new Weight(1, 'g'))
        ) {
            // The app generally sets the weight override, even though it equals the actual parcel weight. In this case
            // we remove the weight override again and keep the original weight distribution because a redistribution
            // is not necessary.
            $parcel->setWeightOverwrite(null);

            return;
        }

        $itemCount = array_sum(array_map(fn(ParcelItem $item) => $item->getQuantity(), $parcel->getItems()));
        if ($itemCount === 0) {
            return;
        }
        $singleItemWeight = $parcel->getWeightOverwrite()->multiplyWithScalar(1 / $itemCount);
        foreach ($parcel->getItems() as $item) {
            $item->setUnitWeight($singleItemWeight);
        }
    }

    private function getActualParcelWeight(Parcel $parcel): ?Weight
    {
        $originalWeightOverride = $parcel->getWeightOverwrite();
        $parcel->setWeightOverwrite(null);
        $actualWeight = $parcel->getTotalWeight();
        $parcel->setWeightOverwrite($originalWeightOverride);

        return $actualWeight;
    }
}
