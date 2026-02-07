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
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\ShippingBundle\Config\Config;
use Pickware\ShippingBundle\Shipment\Model\ShipmentCollection;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResult;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResultSet;
use Pickware\UpsBundle\Api\Requests\CancelShipmentRequest;
use Pickware\UpsBundle\Api\Requests\CreateShipmentRequest;
use Pickware\UpsBundle\Api\UpsApiClientException;
use Pickware\UpsBundle\Api\UpsApiClientFactory;
use Pickware\UpsBundle\Config\UpsConfig;
use Shopware\Core\Framework\Context;

class UpsShipmentRegistrationService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly UpsApiClientFactory $upsApiClientFactory,
        private readonly UpsShipmentFactory $upsShipmentFactory,
        private readonly UpsResponseProcessor $upsResponseProcessor,
    ) {}

    public function registerUpsShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        $upsConfig = new UpsConfig($carrierConfig);
        $apiClient = $this->upsApiClientFactory->createUpsApiClient($upsConfig->getApiCredentials());

        $shipmentsOperationResultSet = new ShipmentsOperationResultSet();
        /** @var ShipmentCollection $shipments */
        $shipments = $this->entityManager->findBy(ShipmentDefinition::class, ['id' => $shipmentIds], $context);

        foreach ($shipments as $shipment) {
            $receiverAddress = $shipment->getShipmentBlueprint()->getReceiverAddress();
            if ($shipment->getIsReturnShipment()) {
                $operationDescription = sprintf(
                    'Create return labels for shipment from %s %s',
                    $receiverAddress->getFirstName(),
                    $receiverAddress->getLastName(),
                );
            } else {
                $operationDescription = sprintf(
                    'Create labels for shipment to %s %s',
                    $receiverAddress->getFirstName(),
                    $receiverAddress->getLastName(),
                );
            }

            try {
                if ($shipment->getIsReturnShipment()) {
                    $upsShipment = $this->upsShipmentFactory->createUpsReturnShipmentFromShipmentBlueprint(
                        $shipment->getShipmentBlueprint(),
                        $upsConfig,
                    );
                } else {
                    $upsShipment = $this->upsShipmentFactory->createUpsShipmentFromShipmentBlueprint(
                        $shipment->getShipmentBlueprint(),
                        $upsConfig,
                    );
                }

                $createShipmentRequest = new CreateShipmentRequest($upsShipment);
            } catch (UpsAdapterException $exception) {
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
            } catch (UpsApiClientException $exception) {
                $shipmentsOperationResultSet->addShipmentOperationResult(
                    ShipmentsOperationResult::createFailedOperationResult(
                        [$shipment->getId()],
                        $operationDescription,
                        $exception->serializeToJsonApiErrors()->getErrors(),
                    ),
                );

                continue;
            }

            $this->upsResponseProcessor->processShipmentCreationResponse(
                Json::decodeToObject((string) $shipmentCreationResponse->getBody()),
                $shipment->getId(),
                $upsConfig,
                $context,
            );
            $shipmentsOperationResultSet->addShipmentOperationResult(
                ShipmentsOperationResult::createSuccessfulOperationResult(
                    [$shipment->getId()],
                    $operationDescription,
                ),
            );
        }

        return $shipmentsOperationResultSet;
    }

    public function cancelUpsShipment(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        $upsConfig = new UpsConfig($carrierConfig);
        if ($upsConfig->useTestingEndpoint()) {
            $shipmentsOperationResultSet = new ShipmentsOperationResultSet();
            $shipmentsOperationResultSet->addShipmentOperationResult(
                ShipmentsOperationResult::createSuccessfulOperationResult(
                    $shipmentIds,
                    'Cancel labels',
                ),
            );

            return $shipmentsOperationResultSet;
        }

        $apiClient = $this->upsApiClientFactory->createUpsApiClient($upsConfig->getApiCredentials());
        $shipmentsOperationResultSet = new ShipmentsOperationResultSet();
        /** @var ShipmentCollection $shipments */
        $shipments = $this->entityManager->findBy(
            ShipmentDefinition::class,
            ['id' => $shipmentIds],
            $context,
            ['trackingCodes'],
        );
        foreach ($shipments as $shipment) {
            try {
                $shipmentCreationResponse = $apiClient->sendRequest(
                    new CancelShipmentRequest($shipment->getMetaInformation()['ShipmentIdentificationNumber']),
                );
                $cancellationResult = Json::decodeToObject((string) $shipmentCreationResponse->getBody());
                $operationDescription = sprintf(
                    'Cancel shipment %s',
                    $shipment->getId(),
                );

                if (property_exists($cancellationResult, 'VoidShipmentResponse')) {
                    if ($cancellationResult->VoidShipmentResponse->Response->ResponseStatus->Code === '1') {
                        $shipmentsOperationResultSet->addShipmentOperationResult(
                            ShipmentsOperationResult::createSuccessfulOperationResult(
                                [$shipment->getId()],
                                $operationDescription,
                            ),
                        );
                    } else {
                        $shipmentsOperationResultSet->addShipmentOperationResult(
                            ShipmentsOperationResult::createFailedOperationResult(
                                [$shipment->getId()],
                                $operationDescription,
                                [
                                    new JsonApiError([
                                        'detail' => $cancellationResult->VoidShipmentResponse->Response->Description,
                                    ]),
                                ],
                            ),
                        );
                    }
                } else {
                    $shipmentsOperationResultSet->addShipmentOperationResult(
                        ShipmentsOperationResult::createFailedOperationResult(
                            [$shipment->getId()],
                            $operationDescription,
                            [new JsonApiError(['detail' => $cancellationResult->response->errors[0]->message])],
                        ),
                    );
                }
            } catch (UpsApiClientException $exception) {
                $shipmentsOperationResultSet->addShipmentOperationResult(
                    ShipmentsOperationResult::createFailedOperationResult(
                        [$shipment->getId()],
                        sprintf(
                            'Cancel shipment %s',
                            $shipment->getId(),
                        ),
                        $exception->serializeToJsonApiErrors()->getErrors(),
                    ),
                );
            }
        }

        return $shipmentsOperationResultSet;
    }
}
