<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\SendcloudBundle\Adapter;

use GuzzleHttp\Psr7\Request;
use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Document\DocumentContentsService;
use Pickware\DocumentBundle\Document\Model\DocumentEntity;
use Pickware\DocumentBundle\Document\PageFormat;
use Pickware\SendcloudBundle\Api\Requests\DownloadLabelRequest;
use Pickware\SendcloudBundle\Api\Requests\DownloadParcelDocumentsRequest;
use Pickware\SendcloudBundle\Api\Requests\RetrieveShipmentRequest;
use Pickware\SendcloudBundle\Api\SendcloudApiClientException;
use Pickware\ShippingBundle\Installation\Documents\CommercialInvoiceDocumentType;
use Pickware\ShippingBundle\Installation\Documents\CustomsDeclarationDocumentType;
use Pickware\ShippingBundle\Installation\Documents\ShippingLabelDocumentType;
use Pickware\ShippingBundle\Installation\Documents\WaybillDocumentType;
use Pickware\ShippingBundle\Rest\RestApiClient;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\Model\ShipmentEntity;
use Pickware\ShippingBundle\Shipment\Model\ShippingDirection;
use Pickware\ShippingBundle\Shipment\Model\TrackingCodeDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use stdClass;

class SendcloudResponseProcessor
{
    private const LABEL_FILE_NAME_PREFIX = 'shipping-label-sendcloud';
    private const INVOICE_FILE_NAME_PREFIX = 'invoice-sendcloud';
    private const WAYBILL_FILE_NAME_PREFIX = 'waybill-sendcloud';
    private const CN23_FILE_NAME_PREFIX = 'cn23-sendcloud';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly DocumentContentsService $documentContentsService,
    ) {}

    public function processShipmentCreationResponse(
        RestApiClient $apiClient,
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

        $trackingCodesPayload = [];
        $parcelIds = [];
        foreach ($shipmentCreationResponse->parcels as $parcel) {
            $parcelId = $parcel->id;

            // If the parcel label creation and announcement was successful that status id is 1000
            // Otherwise we try to retrieve more error information from the API
            if ($parcel->status->id !== 1000) {
                try {
                    $response = $apiClient->sendRequest(new RetrieveShipmentRequest($parcelId));
                } catch (SendcloudApiClientException $e) {
                    throw SendcloudAdapterException::parcelStatusRetrievalFailed($e);
                }

                throw SendcloudAdapterException::fromShipmentResponse($response);
            }

            $documentId = $this->createDocument(
                fileName: sprintf(
                    '%s-%s.%s',
                    self::LABEL_FILE_NAME_PREFIX,
                    $shipment->getShipmentBlueprint()->getCustomerReference(),
                    'pdf',
                ),
                documentTypeTechnicalName: ShippingLabelDocumentType::TECHNICAL_NAME,
                pageFormat: SendcloudLabelSize::A6->getPageFormat(),
                pdf: $this->downloadDocuments($apiClient, new DownloadLabelRequest($parcelId)),
                shipmentId: $shipment->getId(),
                context: $context,
            );

            $trackingCodesPayload[] = [
                'id' => Uuid::randomHex(),
                'trackingCode' => $parcel->tracking_number,
                'trackingUrl' => $parcel->tracking_url,
                'shipmentId' => $shipmentId,
                'shippingDirection' => ShippingDirection::Outgoing,
                'documents' => [
                    ['id' => $documentId],
                ],
            ];
            $parcelIds[] = $parcelId;

            foreach ($parcel->documents as $document) {
                switch ($document->type) {
                    case 'cn23-default':
                    case 'cn23':
                        $fileNamePrefix = self::CN23_FILE_NAME_PREFIX;
                        $documentTypeTechnicalName = CustomsDeclarationDocumentType::TECHNICAL_NAME;
                        break;
                    case 'commercial-invoice':
                        $fileNamePrefix = self::INVOICE_FILE_NAME_PREFIX;
                        $documentTypeTechnicalName = CommercialInvoiceDocumentType::TECHNICAL_NAME;
                        break;
                    case 'air-waybill':
                        $fileNamePrefix = self::WAYBILL_FILE_NAME_PREFIX;
                        $documentTypeTechnicalName = WaybillDocumentType::TECHNICAL_NAME;
                        break;
                    default:
                        // Skip unknown or unwanted document types
                        continue 2;
                }

                $this->createDocument(
                    fileName: sprintf(
                        '%s-%s.%s',
                        $fileNamePrefix,
                        $shipment->getShipmentBlueprint()->getCustomerReference(),
                        'pdf',
                    ),
                    documentTypeTechnicalName: $documentTypeTechnicalName,
                    pageFormat: SendcloudLabelSize::A4->getPageFormat(),
                    pdf: $this->downloadDocuments(
                        $apiClient,
                        new DownloadParcelDocumentsRequest($parcelId, $document->type),
                    ),
                    shipmentId: $shipment->getId(),
                    context: $context,
                );
            }
        }

        $this->entityManager->runInTransactionWithRetry(function() use ($shipmentId, $parcelIds, $trackingCodesPayload, $context): void {
            $this->entityManager->update(
                ShipmentDefinition::class,
                [
                    [
                        'id' => $shipmentId,
                        'metaInformation' => [
                            'ParcelIds' => $parcelIds,
                        ],
                    ],
                ],
                $context,
            );

            $this->entityManager->create(TrackingCodeDefinition::class, $trackingCodesPayload, $context);
        });
    }

    private function downloadDocuments(RestApiClient $apiClient, Request $documentRequest): string
    {
        try {
            $pdf = $apiClient->sendRequest($documentRequest);
        } catch (SendcloudApiClientException $e) {
            throw SendcloudAdapterException::documentDownloadFailed($e);
        }

        return (string) $pdf->getBody();
    }

    private function createDocument(
        string $fileName,
        string $documentTypeTechnicalName,
        PageFormat $pageFormat,
        string $pdf,
        string $shipmentId,
        Context $context,
    ): string {
        return $this->documentContentsService->saveStringAsDocument(
            $pdf,
            $context,
            [
                'fileName' => $fileName,
                'mimeType' => 'application/pdf',
                'orientation' => DocumentEntity::ORIENTATION_PORTRAIT,
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
