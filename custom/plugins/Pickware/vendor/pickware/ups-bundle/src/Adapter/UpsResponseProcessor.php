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

use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Document\DocumentContentsService;
use Pickware\DocumentBundle\Document\PageFormat;
use Pickware\ShippingBundle\Installation\Documents\CommercialInvoiceDocumentType;
use Pickware\ShippingBundle\Installation\Documents\ReturnLabelDocumentType;
use Pickware\ShippingBundle\Installation\Documents\ShippingLabelDocumentType;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\Model\ShipmentEntity;
use Pickware\ShippingBundle\Shipment\Model\ShippingDirection;
use Pickware\ShippingBundle\Shipment\Model\TrackingCodeDefinition;
use Pickware\UpsBundle\Config\UpsConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use stdClass;

class UpsResponseProcessor
{
    private const LABEL_FILE_NAME_PREFIX = 'shipping-label-ups';
    private const RETURN_LABEL_FILE_NAME_PREFIX = 'return-shipping-label-ups';
    private const INVOICE_FILE_NAME_PREFIX = 'invoice-ups';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly DocumentContentsService $documentContentsService,
        private readonly UpsLabelFactory $upsLabelFactory,
    ) {}

    public function processShipmentCreationResponse(
        stdClass $shipmentCreationResponse,
        string $shipmentId,
        UpsConfig $upsConfig,
        Context $context,
    ): void {
        /** @var ShipmentEntity $shipment */
        $shipment = $this->entityManager->getByPrimaryKey(
            ShipmentDefinition::class,
            $shipmentId,
            $context,
        );

        $this->entityManager->update(
            ShipmentDefinition::class,
            [
                [
                    'id' => $shipment->getId(),
                    'metaInformation' => [
                        'ShipmentIdentificationNumber' => $shipmentCreationResponse->ShipmentResponse->ShipmentResults->ShipmentIdentificationNumber,
                    ],
                ],
            ],
            $context,
        );

        if (is_array($shipmentCreationResponse->ShipmentResponse->ShipmentResults->PackageResults)) {
            $packageResult = $shipmentCreationResponse->ShipmentResponse->ShipmentResults->PackageResults;
        } else {
            $packageResult = [$shipmentCreationResponse->ShipmentResponse->ShipmentResults->PackageResults];
        }

        $trackingCodesPayloads = [];
        $labelSize = $upsConfig->getLabelSize();
        foreach ($packageResult as $label) {
            $pdf = $this->upsLabelFactory->convertUpsLabelImageToPdf(
                base64_decode($label->ShippingLabel->GraphicImage),
                $labelSize,
            );

            if ($shipment->getIsReturnShipment()) {
                $documentId = $this->createReturnLabelDocument($pdf, $shipment, $labelSize, $context);
            } else {
                $documentId = $this->createLabelDocument($pdf, $shipment, $labelSize, $context);
            }

            $trackingCodesPayload = [
                'id' => Uuid::randomHex(),
                'trackingCode' => $label->TrackingNumber,
                'trackingUrl' => UpsAdapter::getTrackingUrlForTrackingNumbers([$label->TrackingNumber]),
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

        if ($shipmentCreationResponse->ShipmentResponse->ShipmentResults->Form ?? false) {
            $base64 = $shipmentCreationResponse->ShipmentResponse->ShipmentResults->Form->Image->GraphicImage;
            $this->createCommercialInvoiceDocument(base64_decode($base64), $shipment, $context);
        }

        $this->entityManager->create(TrackingCodeDefinition::class, $trackingCodesPayloads, $context);
    }

    private function createLabelDocument(
        string $pdf,
        ShipmentEntity $shipment,
        UpsLabelSize $labelSize,
        Context $context,
    ): string {
        return $this->documentContentsService->saveStringAsDocument(
            $pdf,
            $context,
            [
                'fileName' => sprintf(
                    '%s-%s.%s',
                    self::LABEL_FILE_NAME_PREFIX,
                    $shipment->getShipmentBlueprint()->getCustomerReference(),
                    'pdf',
                ),
                'mimeType' => 'application/pdf',
                'documentTypeTechnicalName' => ShippingLabelDocumentType::TECHNICAL_NAME,
                'pageFormat' => $labelSize->getPageFormat(),
                'orientation' => 'portrait',
                'extensions' => [
                    'pickwareShippingShipments' => [
                        [
                            'id' => $shipment->getId(),
                        ],
                    ],
                ],
            ],
        );
    }

    private function createReturnLabelDocument(
        string $pdf,
        ShipmentEntity $shipment,
        UpsLabelSize $labelSize,
        Context $context,
    ): string {
        return $this->documentContentsService->saveStringAsDocument(
            $pdf,
            $context,
            [
                'fileName' => sprintf(
                    '%s-%s.%s',
                    self::RETURN_LABEL_FILE_NAME_PREFIX,
                    $shipment->getShipmentBlueprint()->getCustomerReference(),
                    'pdf',
                ),
                'mimeType' => 'application/pdf',
                'documentTypeTechnicalName' => ReturnLabelDocumentType::TECHNICAL_NAME,
                'pageFormat' => $labelSize->getPageFormat(),
                'orientation' => 'portrait',
                'extensions' => [
                    'pickwareShippingShipments' => [
                        [
                            'id' => $shipment->getId(),
                        ],
                    ],
                ],
            ],
        );
    }

    private function createCommercialInvoiceDocument(
        string $pdf,
        ShipmentEntity $shipment,
        Context $context,
    ): void {
        $this->documentContentsService->saveStringAsDocument(
            $pdf,
            $context,
            [
                'fileName' => sprintf(
                    '%s-%s.%s',
                    self::INVOICE_FILE_NAME_PREFIX,
                    $shipment->getShipmentBlueprint()->getCustomerReference(),
                    'pdf',
                ),
                'mimeType' => 'application/pdf',
                'documentTypeTechnicalName' => CommercialInvoiceDocumentType::TECHNICAL_NAME,
                'pageFormat' => PageFormat::createDinPageFormat('A4'),
                'orientation' => 'portrait',
                'extensions' => [
                    'pickwareShippingShipments' => [
                        [
                            'id' => $shipment->getId(),
                        ],
                    ],
                ],
            ],
        );
    }
}
