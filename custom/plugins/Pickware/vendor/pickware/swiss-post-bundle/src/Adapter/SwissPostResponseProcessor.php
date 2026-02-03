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

use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Document\DocumentContentsService;
use Pickware\DocumentBundle\Document\Model\DocumentEntity;
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

class SwissPostResponseProcessor
{
    private const LABEL_FILE_NAME_PREFIX = 'shipping-label-swiss-post';
    private const RETURN_LABEL_FILE_NAME_PREFIX = 'return-shipping-label-swiss-post';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly DocumentContentsService $documentContentsService,
    ) {}

    public function processShipmentCreationResponse(
        stdClass $shipmentCreationResponse,
        ShipmentsOperationResultSet $shipmentsOperationResultSet,
        Context $context,
    ): void {
        $parcelReference = ParcelReference::fromString($shipmentCreationResponse->item->itemID);

        $shipmentId = $parcelReference->getShipmentId();
        /** @var ShipmentEntity $shipment */
        $shipment = $this->entityManager->getByPrimaryKey(
            ShipmentDefinition::class,
            $shipmentId,
            $context,
        );

        $shipmentsOperationResult = $this->createShipmentsOperationResult(
            $shipmentCreationResponse,
            $shipment,
            $parcelReference->getIndex(),
        );
        $shipmentsOperationResultSet->addShipmentOperationResult($shipmentsOperationResult);
        if (!$shipmentsOperationResult->isSuccessful()) {
            return;
        }

        $trackingCodesPayloads = [];
        foreach ($shipmentCreationResponse->item->label as $label) {
            if ($shipment->getIsReturnShipment()) {
                $documentId = $this->createLabelDocument(
                    label: $label,
                    fileName: sprintf(
                        '%s-%s.%s',
                        self::RETURN_LABEL_FILE_NAME_PREFIX,
                        $shipment->getShipmentBlueprint()->getCustomerReference(),
                        'pdf',
                    ),
                    pageFormat: $shipmentCreationResponse->labelDefinition->labelLayout,
                    documentTypeTechnicalName: ReturnLabelDocumentType::TECHNICAL_NAME,
                    shipmentId: $shipment->getId(),
                    context: $context,
                );
            } else {
                $documentId = $this->createLabelDocument(
                    label: $label,
                    fileName: sprintf(
                        '%s-%s.%s',
                        self::LABEL_FILE_NAME_PREFIX,
                        $shipment->getShipmentBlueprint()->getCustomerReference(),
                        'pdf',
                    ),
                    pageFormat: $shipmentCreationResponse->labelDefinition->labelLayout,
                    documentTypeTechnicalName: ShippingLabelDocumentType::TECHNICAL_NAME,
                    shipmentId: $shipment->getId(),
                    context: $context,
                );
            }

            $trackingCodesPayload = [
                'id' => Uuid::randomHex(),
                'trackingCode' => $shipmentCreationResponse->item->identCode,
                'trackingUrl' => SwissPostAdapter::getTrackingUrlForTrackingNumbers([$shipmentCreationResponse->item->identCode]),
                'shipmentId' => $shipment->getId(),
                'shippingDirection' => $shipment->getIsReturnShipment() ? ShippingDirection::Incoming : ShippingDirection::Outgoing,
                'documents' => [
                    [
                        'id' => $documentId,
                    ],
                ],
            ];

            $trackingCodesPayloads[] = $trackingCodesPayload;
        }

        $this->entityManager->create(TrackingCodeDefinition::class, $trackingCodesPayloads, $context);
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

        if (isset($shipmentResponse->item->errors)) {
            $errors = array_map(
                fn(stdClass $error) => new JsonApiError(['detail' => sprintf('%s: %s', $error->code, $error->message)]),
                $shipmentResponse->item->errors,
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

    private function createLabelDocument(
        string $label,
        string $fileName,
        string $pageFormat,
        string $documentTypeTechnicalName,
        string $shipmentId,
        Context $context,
    ): string {
        return $this->documentContentsService->saveStringAsDocument(
            base64_decode($label),
            $context,
            [
                'fileName' => $fileName,
                'mimeType' => 'application/pdf',
                'orientation' => DocumentEntity::ORIENTATION_PORTRAIT,
                'documentTypeTechnicalName' => $documentTypeTechnicalName,
                'pageFormat' => SwissPostLabelSize::from($pageFormat)->getPageFormat(),
                'extensions' => [
                    'pickwareShippingShipments' => [
                        [
                            'id' => $shipmentId,
                        ],
                    ],
                ],
            ],
        );
    }
}
