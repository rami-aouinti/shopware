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

use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Document\DocumentContentsService;
use Pickware\DocumentBundle\Document\Model\DocumentEntity;
use Pickware\DocumentBundle\Document\PageFormat;
use Pickware\ShippingBundle\Installation\Documents\CustomsDeclarationDocumentType;
use Pickware\ShippingBundle\Installation\Documents\ReturnLabelDocumentType;
use Pickware\ShippingBundle\Installation\Documents\ShippingLabelDocumentType;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\Model\ShipmentEntity;
use Pickware\ShippingBundle\Shipment\Model\ShippingDirection;
use Pickware\ShippingBundle\Shipment\Model\TrackingCodeDefinition;
use Shopware\Core\Framework\Context;
use stdClass;

class AustrianPostResponseProcessor
{
    private const LABEL_FILE_NAME_PREFIX = 'shipping-label-austrian-post';
    private const RETURN_LABEL_FILE_NAME_PREFIX = 'return-label-austrian-post';
    private const EXPORT_FILE_NAME_PREFIX = 'customs-declaration-austrian-post';
    private const TRACKING_CODE_TYPE_IDS = [
        130201,
        213,
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly DocumentContentsService $documentContentsService,
    ) {}

    public function processImportShipmentResponse(
        stdClass $shipmentCreationResponse,
        PageFormat $pageFormat,
        string $shipmentId,
        Context $context,
    ): void {
        /** @var ShipmentEntity $shipment */
        $shipment = $this->entityManager->getByPrimaryKey(
            ShipmentDefinition::class,
            $shipmentId,
            $context,
        );

        $documentId = $this->createDocument(
            fileName: sprintf(
                '%s-%s.%s',
                $shipment->getIsReturnShipment() ? self::RETURN_LABEL_FILE_NAME_PREFIX : self::LABEL_FILE_NAME_PREFIX,
                $shipment->getShipmentBlueprint()->getCustomerReference(),
                'pdf',
            ),
            documentTypeTechnicalName: $shipment->getIsReturnShipment() ? ReturnLabelDocumentType::TECHNICAL_NAME : ShippingLabelDocumentType::TECHNICAL_NAME,
            pageFormat: $pageFormat,
            orientation: DocumentEntity::ORIENTATION_PORTRAIT,
            pdf: $shipmentCreationResponse->pdfData,
            shipmentId: $shipment->getId(),
            context: $context,
        );

        if ($shipmentCreationResponse->shipmentDocuments) {
            $this->createDocument(
                fileName: sprintf(
                    '%s-%s.%s',
                    self::EXPORT_FILE_NAME_PREFIX,
                    $shipment->getShipmentBlueprint()->getCustomerReference(),
                    'pdf',
                ),
                documentTypeTechnicalName: CustomsDeclarationDocumentType::TECHNICAL_NAME,
                pageFormat: PageFormat::createDinPageFormat('A4'),
                orientation: DocumentEntity::ORIENTATION_PORTRAIT,
                pdf: $shipmentCreationResponse->shipmentDocuments,
                shipmentId: $shipment->getId(),
                context: $context,
            );
        }

        $trackingCodesPayloads = [];
        if (!is_array($shipmentCreationResponse->ImportShipmentResult->ColloRow)) {
            $shipmentCreationResponse->ImportShipmentResult->ColloRow = [$shipmentCreationResponse->ImportShipmentResult->ColloRow];
        }
        foreach ($shipmentCreationResponse->ImportShipmentResult->ColloRow as $colloRow) {
            if (!is_array($colloRow->ColloCodeList->ColloCodeRow)) {
                $colloRow->ColloCodeList->ColloCodeRow = [$colloRow->ColloCodeList->ColloCodeRow];
            }
            foreach ($colloRow->ColloCodeList->ColloCodeRow as $colloCodeRow) {
                if (in_array($colloCodeRow->NumberTypeID, self::TRACKING_CODE_TYPE_IDS, true)) {
                    $trackingCodesPayload = [
                        'trackingCode' => $colloCodeRow->Code,
                        'trackingUrl' => sprintf('https://www.post.at/s/sendungssuche?snr=%s', $colloCodeRow->Code),
                        'shipmentId' => $shipmentId,
                        'shippingDirection' => $shipment->getIsReturnShipment() ? ShippingDirection::Incoming : ShippingDirection::Outgoing,
                        'documents' => [
                            ['id' => $documentId],
                        ],
                    ];

                    $trackingCodesPayloads[] = $trackingCodesPayload;
                }
            }
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
            documentContents: base64_decode($pdf),
            context: $context,
            options: [
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
