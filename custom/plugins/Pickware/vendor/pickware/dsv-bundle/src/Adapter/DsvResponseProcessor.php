<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DsvBundle\Adapter;

use GuzzleHttp\Psr7\Request;
use Pickware\DalBundle\EntityManager;
use Pickware\DocumentBundle\Document\DocumentContentsService;
use Pickware\DocumentBundle\Document\Model\DocumentEntity;
use Pickware\DocumentBundle\Document\PageFormat;
use Pickware\DsvBundle\Api\DsvApiClientException;
use Pickware\DsvBundle\Api\DsvApiClientFactory;
use Pickware\DsvBundle\Api\Requests\DownloadLabelRequest;
use Pickware\DsvBundle\Api\Requests\TrackShipmentRequest;
use Pickware\DsvBundle\Config\DsvConfig;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\ShippingBundle\Installation\Documents\ShippingLabelDocumentType;
use Pickware\ShippingBundle\Rest\RestApiClient;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\Model\ShipmentEntity;
use Pickware\ShippingBundle\Shipment\Model\ShippingDirection;
use Pickware\ShippingBundle\Shipment\Model\TrackingCodeDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use stdClass;

class DsvResponseProcessor
{
    private const LABEL_FILE_NAME_PREFIX = 'shipping-label-dsv';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly DocumentContentsService $documentContentsService,
        private readonly DsvApiClientFactory $dsvApiClientFactory,
    ) {}

    public function processShipmentCreationResponse(
        DsvConfig $dsvConfig,
        stdClass $shipmentCreationResponse,
        string $shipmentId,
        Context $context,
    ): void {
        $apiClient = $this->dsvApiClientFactory->createDsvApiClient(
            $dsvConfig->getApiCredentials(),
        );

        /** @var ShipmentEntity $shipment */
        $shipment = $this->entityManager->getByPrimaryKey(
            ShipmentDefinition::class,
            $shipmentId,
            $context,
        );

        $documentId = $this->createDocument(
            fileName: sprintf(
                '%s-%s.%s',
                self::LABEL_FILE_NAME_PREFIX,
                $shipment->getShipmentBlueprint()->getCustomerReference(),
                'pdf',
            ),
            documentTypeTechnicalName: ShippingLabelDocumentType::TECHNICAL_NAME,
            pageFormat: DsvLabelSize::A4->getPageFormat(),
            orientation: DocumentEntity::ORIENTATION_PORTRAIT,
            pdf: $this->downloadDocuments($apiClient, new DownloadLabelRequest($shipmentCreationResponse->bookingId)),
            shipmentId: $shipment->getId(),
            context: $context,
        );

        $trackingResponse = $apiClient->sendRequest(new TrackShipmentRequest($shipmentCreationResponse->bookingId));

        $tracking = Json::decodeToObject(json: (string) $trackingResponse->getBody());

        $trackingUrl = sprintf(
            'https://%smydsv.com/new/tracking/shipment-details-public?id=%s',
            $dsvConfig->getApiCredentials()->shouldUseTestingEndpoint() ? 'demo.' : '',
            $tracking->publicShipmentId,
        );

        $trackingCodesPayload = [];
        foreach ($tracking->packages as $package) {
            $trackingCodesPayload[] = [
                'id' => Uuid::randomHex(),
                'trackingCode' => $package->references[0]->reference,
                'trackingUrl' => $trackingUrl,
                'shipmentId' => $shipmentId,
                'shippingDirection' => ShippingDirection::Outgoing,
                'documents' => [
                    ['id' => $documentId],
                ],
                'metaInformation' => [
                    'bookingId' => $shipmentCreationResponse->bookingId,
                    'trackingCodeType' => $package->references[0]->qualifier ?? null,
                ],
            ];
        }

        $this->entityManager->create(TrackingCodeDefinition::class, $trackingCodesPayload, $context);
    }

    private function downloadDocuments(RestApiClient $apiClient, Request $documentRequest): string
    {
        try {
            $pdf = $apiClient->sendRequest($documentRequest);
        } catch (DsvApiClientException $e) {
            throw DsvAdapterException::documentDownloadFailed($e);
        }

        return (string) $pdf->getBody();
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
            $pdf,
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
