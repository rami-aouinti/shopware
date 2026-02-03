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

use Pickware\DalBundle\EntityManager;
use Pickware\DsvBundle\Api\DsvApiClientException;
use Pickware\DsvBundle\Api\DsvApiClientFactory;
use Pickware\DsvBundle\Api\Requests\CreateShipmentRequest;
use Pickware\DsvBundle\Api\Requests\ValidateShipmentRequest;
use Pickware\DsvBundle\Config\DsvConfig;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\ShippingBundle\Config\Config;
use Pickware\ShippingBundle\Shipment\Model\ShipmentCollection;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResult;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResultSet;
use Shopware\Core\Framework\Context;

class DsvShipmentRegistrationService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly DsvApiClientFactory $dsvApiClientFactory,
        private readonly DsvShipmentFactory $dsvShipmentFactory,
        private readonly DsvResponseProcessor $dsvResponseProcessor,
    ) {}

    public function registerDsvShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        $dsvConfig = new DsvConfig($carrierConfig);
        $apiClient = $this->dsvApiClientFactory->createDsvApiClient(
            $dsvConfig->getApiCredentials(),
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
                $dsvShipment = $this->dsvShipmentFactory->createDsvShipmentFromShipmentBlueprint(
                    $shipment->getShipmentBlueprint(),
                    $dsvConfig,
                );

                $createShipmentRequest = new CreateShipmentRequest($dsvShipment);
                $validateShipmentRequest = new ValidateShipmentRequest($dsvShipment);
            } catch (DsvAdapterException $exception) {
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
                $apiClient->sendRequest($validateShipmentRequest);
                $shipmentCreationResponse = $apiClient->sendRequest($createShipmentRequest);
            } catch (DsvApiClientException $exception) {
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
                $this->dsvResponseProcessor->processShipmentCreationResponse(
                    $dsvConfig,
                    Json::decodeToObject(json: (string) $shipmentCreationResponse->getBody()),
                    $shipment->getId(),
                    $context,
                );
            } catch (DsvAdapterException $processingException) {
                $shipmentsOperationResultSet->addShipmentOperationResult(
                    ShipmentsOperationResult::createFailedOperationResult(
                        [$shipment->getId()],
                        $operationDescription,
                        [$processingException->serializeToJsonApiError()],
                    ),
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
}
