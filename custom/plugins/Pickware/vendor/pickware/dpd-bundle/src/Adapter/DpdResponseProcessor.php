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

use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Document\DocumentContentsService;
use Pickware\DocumentBundle\Document\Model\DocumentEntity;
use Pickware\DpdBundle\Config\DpdConfig;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\ShippingBundle\Installation\Documents\ReturnLabelDocumentType;
use Pickware\ShippingBundle\Installation\Documents\ShippingLabelDocumentType;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\Model\ShipmentEntity;
use Pickware\ShippingBundle\Shipment\Model\ShippingDirection;
use Pickware\ShippingBundle\Shipment\Model\TrackingCodeDefinition;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResult;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResultSet;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use stdClass;

class DpdResponseProcessor
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly DocumentContentsService $documentContentsService,
    ) {}

    public function processCreateShipmentOrderResponse(
        stdClass $shipmentCreationResponse,
        ShipmentsOperationResultSet $shipmentsOperationResultSet,
        DpdConfig $dpdConfig,
        Context $context,
        bool $isReturnShipment = false,
    ): ShipmentsOperationResultSet {
        $shipmentResponses = $shipmentCreationResponse->orderResult->shipmentResponses;

        $shipmentResponses = is_array($shipmentResponses) ? $shipmentResponses : [$shipmentResponses];
        foreach ($shipmentResponses as $shipmentResponse) {
            $parcelReference = ParcelReference::fromString($shipmentResponse->identificationNumber);

            /** @var ShipmentEntity $shipment */
            $shipment = $this->entityManager->getByPrimaryKey(
                ShipmentDefinition::class,
                $parcelReference->getShipmentId(),
                $context,
            );

            $shipmentsOperationResult = $this->createShipmentsOperationResult(
                $shipmentResponse,
                $shipment,
                $parcelReference->getIndex(),
            );
            $shipmentsOperationResultSet->addShipmentOperationResult($shipmentsOperationResult);
            if (!$shipmentsOperationResult->isSuccessful()) {
                continue;
            }

            $customerReference = $shipment->getShipmentBlueprint()->getCustomerReference();
            $labelDocumentFileNameSuffix = $customerReference ? sprintf('-%s', $customerReference) : '';
            $this->saveLabelDocumentAndTrackingCode($shipment, $shipmentResponse, $labelDocumentFileNameSuffix, $dpdConfig, $context, $isReturnShipment);
        }

        return $shipmentsOperationResultSet;
    }

    private function createShipmentsOperationResult(
        stdClass $shipmentResponse,
        ShipmentEntity $shipment,
        int $parcelIndex,
    ): ShipmentsOperationResult {
        $operationDescription = sprintf(
            'Create label to %s %s, parcel %d',
            $shipment->getShipmentBlueprint()->getReceiverAddress()->getFirstName(),
            $shipment->getShipmentBlueprint()->getReceiverAddress()->getLastName(),
            $parcelIndex + 1,
        );

        if (isset($shipmentResponse->faults)) {
            $errors = array_map(
                fn(stdClass $fault) => new JsonApiError(
                    ['detail' => sprintf('%s: %s', $fault->faultCode, $fault->message)],
                ),
                is_array($shipmentResponse->faults) ? $shipmentResponse->faults : [$shipmentResponse->faults],
            );

            return ShipmentsOperationResult::createFailedOperationResult(
                [$shipment->getId()],
                $operationDescription,
                $errors,
            );
        }

        return ShipmentsOperationResult::createSuccessfulOperationResult(
            [$shipment->getId()],
            $operationDescription,
        );
    }

    private function saveLabelDocumentAndTrackingCode(
        ShipmentEntity $shipment,
        stdClass $shipmentData,
        string $labelDocumentFileNameSuffix,
        DpdConfig $dpdConfig,
        Context $context,
        bool $isReturnShipment = false,
    ): void {
        $documentTypeTechnicalName = $isReturnShipment ? ReturnLabelDocumentType::TECHNICAL_NAME : ShippingLabelDocumentType::TECHNICAL_NAME;
        $labelType = $isReturnShipment ? 'return-label' : 'shipping-label';

        $documentId = $this->documentContentsService->saveStringAsDocument(
            $shipmentData->parcelInformation->output->content,
            $context,
            [
                'fileName' => sprintf('%s-dpd%s.pdf', $labelType, $labelDocumentFileNameSuffix),
                'mimeType' => 'application/pdf',
                'orientation' => DocumentEntity::ORIENTATION_PORTRAIT,
                'documentTypeTechnicalName' => $documentTypeTechnicalName,
                'pageFormat' => $dpdConfig->getLabelSize()->getPageFormat(),
                'extensions' => ['pickwareShippingShipments' => [['id' => $shipment->getId()]]],
            ],
        );

        $parcelLabelNumber = $shipmentData->parcelInformation->parcelLabelNumber;
        $trackingCodesPayload = [
            'id' => Uuid::randomHex(),
            'trackingCode' => $parcelLabelNumber,
            'trackingUrl' => sprintf('https://tracking.dpd.de/status/en_US/parcel/%s', $parcelLabelNumber),
            'metaInformation' => [
                'type' => 'parcelLabelNumber',
            ],
            'shipmentId' => $shipment->getId(),
            'shippingDirection' => ShippingDirection::Outgoing,
            'documents' => [['id' => $documentId]],
        ];
        $this->entityManager->create(TrackingCodeDefinition::class, [$trackingCodesPayload], $context);
    }
}
