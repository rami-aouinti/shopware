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

use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Document\DocumentContentsService;
use Pickware\DocumentBundle\Document\Model\DocumentEntity;
use Pickware\DocumentBundle\Document\PageFormat;
use Pickware\ShippingBundle\Installation\Documents\CommercialInvoiceDocumentType;
use Pickware\ShippingBundle\Installation\Documents\OtherDocumentType;
use Pickware\ShippingBundle\Installation\Documents\ReturnLabelDocumentType;
use Pickware\ShippingBundle\Installation\Documents\ShippingLabelDocumentType;
use Pickware\ShippingBundle\Installation\Documents\WaybillDocumentType;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\Model\ShipmentEntity;
use Pickware\ShippingBundle\Shipment\Model\ShippingDirection;
use Pickware\ShippingBundle\Shipment\Model\TrackingCodeDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use stdClass;

class DhlExpressResponseProcessor
{
    private EntityManager $entityManager;
    private DocumentContentsService $documentContentsService;

    private const LABEL_FILE_NAME_PREFIX = 'shipping-label-dhl-express';
    private const RETURN_LABEL_FILE_NAME_PREFIX = 'return-shipping-label-dhl-express';
    private const INVOICE_FILE_NAME_PREFIX = 'invoice-dhl-express';
    private const WAYBILL_FILE_NAME_PREFIX = 'waybill-dhl-express';
    private const OTHER_FILE_NAME_PREFIX = 'other-dhl-express';

    public function __construct(
        EntityManager $entityManager,
        DocumentContentsService $documentContentsService,
    ) {
        $this->entityManager = $entityManager;
        $this->documentContentsService = $documentContentsService;
    }

    public function processShipmentCreationResponse(
        stdClass $shipmentCreationResponse,
        string $shipmentId,
        Context $context,
    ): void {
        /** @var ShipmentEntity $shipment */
        $shipment = $this->entityManager->getByPrimaryKey(
            ShipmentDefinition::class,
            $shipmentId,
            $context,
        );

        $documentId = null;
        foreach ($shipmentCreationResponse->documents as $document) {
            switch ($document->typeCode) {
                case 'label':
                    if (!$documentId) {
                        if ($shipment->getIsReturnShipment()) {
                            $documentId = $this->createDocument(
                                fileName: sprintf(
                                    '%s-%s.%s',
                                    self::RETURN_LABEL_FILE_NAME_PREFIX,
                                    $shipment->getShipmentBlueprint()->getCustomerReference(),
                                    'pdf',
                                ),
                                documentTypeTechnicalName: ReturnLabelDocumentType::TECHNICAL_NAME,
                                pageFormat: DhlExpressLabelSize::A5->getPageFormat(),
                                orientation: DocumentEntity::ORIENTATION_PORTRAIT,
                                pdf: $document->content,
                                shipmentId: $shipment->getId(),
                                context: $context,
                            );
                        } else {
                            $documentId = $this->createDocument(
                                fileName: sprintf(
                                    '%s-%s.%s',
                                    self::LABEL_FILE_NAME_PREFIX,
                                    $shipment->getShipmentBlueprint()->getCustomerReference(),
                                    'pdf',
                                ),
                                documentTypeTechnicalName: ShippingLabelDocumentType::TECHNICAL_NAME,
                                pageFormat: DhlExpressLabelSize::A5->getPageFormat(),
                                orientation: DocumentEntity::ORIENTATION_PORTRAIT,
                                pdf: $document->content,
                                shipmentId: $shipment->getId(),
                                context: $context,
                            );
                        }
                    } else {
                        // We request the label and waybill to be split by DHL Express but they both have the type
                        // label. We assume that shipping label is always first in the list of returned labels.
                        // Because of this we assume that when a documentId is set the next label is the waybill
                        // document.
                        $this->createDocument(
                            fileName: sprintf(
                                '%s-%s.%s',
                                self::WAYBILL_FILE_NAME_PREFIX,
                                $shipment->getShipmentBlueprint()->getCustomerReference(),
                                'pdf',
                            ),
                            documentTypeTechnicalName: WaybillDocumentType::TECHNICAL_NAME,
                            pageFormat: PageFormat::createDinPageFormat('A5'),
                            orientation: DocumentEntity::ORIENTATION_PORTRAIT,
                            pdf: $document->content,
                            shipmentId: $shipment->getId(),
                            context: $context,
                        );
                    }
                    break;
                case 'invoice':
                    $this->createDocument(
                        fileName: sprintf(
                            '%s-%s.%s',
                            self::INVOICE_FILE_NAME_PREFIX,
                            $shipment->getShipmentBlueprint()->getCustomerReference(),
                            'pdf',
                        ),
                        documentTypeTechnicalName: CommercialInvoiceDocumentType::TECHNICAL_NAME,
                        pageFormat: PageFormat::createDinPageFormat('A4'),
                        orientation: DocumentEntity::ORIENTATION_LANDSCAPE,
                        pdf: $document->content,
                        shipmentId: $shipment->getId(),
                        context: $context,
                    );
                    break;
                default:
                    $this->createDocument(
                        fileName: sprintf(
                            '%s-%s.%s',
                            self::OTHER_FILE_NAME_PREFIX,
                            $shipment->getShipmentBlueprint()->getCustomerReference(),
                            'pdf',
                        ),
                        documentTypeTechnicalName: OtherDocumentType::TECHNICAL_NAME,
                        pageFormat: PageFormat::createDinPageFormat('A5'),
                        orientation: DocumentEntity::ORIENTATION_PORTRAIT,
                        pdf: $document->content,
                        shipmentId: $shipment->getId(),
                        context: $context,
                    );
            }
        }

        $trackingCodesPayloads = [];
        foreach ($shipmentCreationResponse->packages as $package) {
            $trackingCodesPayload = [
                'id' => Uuid::randomHex(),
                'trackingCode' => $package->trackingNumber,
                'trackingUrl' => DhlExpressAdapter::getTrackingUrlForTrackingNumbers([$package->trackingNumber]),
                'metaInformation' => [
                    'parcelNumber' => $package->referenceNumber,
                ],
                'shipmentId' => $shipmentId,
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

    private function createDocument(
        string $fileName,
        string $documentTypeTechnicalName,
        PageFormat $pageFormat,
        string $orientation,
        string $pdf,
        string $shipmentId,
        Context $context,
    ): string {
        return $this->documentContentsService->saveStringAsDocument(
            base64_decode($pdf),
            $context,
            [
                'fileName' => $fileName,
                'mimeType' => 'application/pdf',
                'orientation' => $orientation,
                'documentTypeTechnicalName' => $documentTypeTechnicalName,
                'pageFormat' => $pageFormat,
                'extensions' => [
                    'pickwareShippingShipments' => [
                        ['id' => $shipmentId],
                    ],
                ],
            ],
        );
    }
}
