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

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\SendcloudBundle\Api\Requests\CancelShipmentRequest;
use Pickware\SendcloudBundle\Api\Requests\CreateShipmentRequest;
use Pickware\SendcloudBundle\Api\SendcloudApiClientException;
use Pickware\SendcloudBundle\Api\SendcloudApiClientFactory;
use Pickware\SendcloudBundle\Config\SendcloudConfig;
use Pickware\ShippingBundle\Config\Config;
use Pickware\ShippingBundle\Rest\RestApiClient;
use Pickware\ShippingBundle\Shipment\Model\ShipmentCollection;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResult;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResultSet;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Response;

class SendcloudShipmentRegistrationService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly SendcloudApiClientFactory $sendcloudApiClientFactory,
        private readonly SendcloudShipmentFactory $sendcloudShipmentFactory,
        private readonly SendcloudResponseProcessor $sendcloudResponseProcessor,
    ) {}

    public function registerSendcloudShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        $sendcloudConfig = new SendcloudConfig($carrierConfig);
        $apiClient = $this->sendcloudApiClientFactory->createSendcloudApiClient(
            $sendcloudConfig->getApiCredentials(),
        );

        $shipmentsOperationResultSet = new ShipmentsOperationResultSet();
        /** @var ShipmentCollection $shipments */
        $shipments = $this->entityManager->findBy(ShipmentDefinition::class, ['id' => $shipmentIds], $context);

        foreach ($shipments as $shipment) {
            $receiverAddress = $shipment->getShipmentBlueprint()->getReceiverAddress();
            $operationDescription = sprintf(
                'Create labels for shipment to %s %s',
                $receiverAddress->getFirstName(),
                $receiverAddress->getLastName(),
            );

            try {
                $sendcloudShipment = $this->sendcloudShipmentFactory->createSendcloudShipmentFromShipmentBlueprint(
                    $shipment->getShipmentBlueprint(),
                );

                $createShipmentRequest = new CreateShipmentRequest($sendcloudShipment);
            } catch (SendcloudAdapterException $exception) {
                $shipmentsOperationResultSet->addShipmentOperationResult(
                    ShipmentsOperationResult::createFailedOperationResult(
                        [$shipment->getId()],
                        $operationDescription,
                        [$exception->serializeToJsonApiError()],
                    ),
                );

                continue;
            }

            try {
                $shipmentCreationResponse = $apiClient->sendRequest($createShipmentRequest);
            } catch (SendcloudApiClientException $exception) {
                $shipmentsOperationResultSet->addShipmentOperationResult(
                    ShipmentsOperationResult::createFailedOperationResult(
                        [$shipment->getId()],
                        $operationDescription,
                        [$exception->serializeToJsonApiError()],
                    ),
                );

                continue;
            }

            try {
                $shipmentCreationResponse = Json::decodeToObject(json: (string) $shipmentCreationResponse->getBody());

                $this->sendcloudResponseProcessor->processShipmentCreationResponse(
                    $apiClient,
                    $shipmentCreationResponse,
                    $shipment->getId(),
                    $context,
                );
            } catch (SendcloudAdapterException $processingException) {
                $parcelIdsToCancel = [];

                foreach ($shipmentCreationResponse->parcels as $parcel) {
                    $parcelIdsToCancel[] = $parcel->id;
                }

                $this->rollbackShipmentInSendcloud(
                    parcelIds: $parcelIdsToCancel,
                    shipmentId: $shipment->getId(),
                    shipmentsOperationResultSet: $shipmentsOperationResultSet,
                    operationDescription: $operationDescription,
                    apiClient: $apiClient,
                    processingException: $processingException,
                );

                continue;
            }

            $shipmentsOperationResultSet->addShipmentOperationResult(
                ShipmentsOperationResult::createSuccessfulOperationResult(
                    [$shipment->getId()],
                    $operationDescription,
                ),
            );
        }

        return $shipmentsOperationResultSet;
    }

    public function cancelSendcloudShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        $sendcloudConfig = new SendcloudConfig($carrierConfig);
        $apiClient = $this->sendcloudApiClientFactory->createSendcloudApiClient(
            $sendcloudConfig->getApiCredentials(),
        );
        /** @var ShipmentCollection $shipments */
        $shipments = $this->entityManager->findBy(ShipmentDefinition::class, ['id' => $shipmentIds], $context);

        $shipmentsOperationResultSet = new ShipmentsOperationResultSet();
        foreach ($shipments as $shipment) {
            $parcelIds = $shipment->getMetaInformation()['ParcelIds'];
            try {
                $this->cancelShipments($parcelIds, $apiClient);

                $shipmentsOperationResultSet->addShipmentOperationResult(
                    ShipmentsOperationResult::createSuccessfulOperationResult(
                        [$shipment->getId()],
                        sprintf(
                            'Cancel shipment %s',
                            $shipment->getId(),
                        ),
                    ),
                );
            } catch (SendcloudAdapterException $exception) {
                $shipmentsOperationResultSet->addShipmentOperationResult(
                    ShipmentsOperationResult::createFailedOperationResult(
                        [$shipment->getId()],
                        sprintf(
                            'Cancel shipment %s',
                            $shipment->getId(),
                        ),
                        [$exception->serializeToJsonApiError()],
                    ),
                );
            }
        }

        return $shipmentsOperationResultSet;
    }

    private function cancelShipments(
        array $parcelIds,
        RestApiClient $apiClient,
    ): void {
        $failedIds = [];
        foreach ($parcelIds as $parcelId) {
            try {
                $apiClient->sendRequest(new CancelShipmentRequest($parcelId));
            } catch (SendcloudApiClientException $exception) {
                if ($exception->getPrevious() && $exception->getPrevious()->getCode() === Response::HTTP_GONE) {
                    continue;
                }

                $failedIds[] = $parcelId;
            }
        }

        if (count($failedIds) > 0) {
            throw SendcloudAdapterException::failedToCancelParcels($failedIds);
        }
    }

    private function rollbackShipmentInSendcloud(
        array $parcelIds,
        string $shipmentId,
        ShipmentsOperationResultSet $shipmentsOperationResultSet,
        string $operationDescription,
        RestApiClient $apiClient,
        SendcloudAdapterException $processingException,
    ): void {
        try {
            $this->cancelShipments($parcelIds, $apiClient);

            $shipmentsOperationResultSet->addShipmentOperationResult(
                ShipmentsOperationResult::createFailedOperationResult(
                    [$shipmentId],
                    $operationDescription,
                    [
                        $processingException->serializeToJsonApiError(),
                        SendcloudAdapterException::parcelsRolledback()->serializeToJsonApiError(),
                    ],
                ),
            );
        } catch (SendcloudAdapterException $cancelException) {
            $shipmentsOperationResultSet->addShipmentOperationResult(
                ShipmentsOperationResult::createFailedOperationResult(
                    [$shipmentId],
                    $operationDescription,
                    [
                        $processingException->serializeToJsonApiError(),
                        $cancelException->serializeToJsonApiError(),
                    ],
                ),
            );
        }
    }
}
