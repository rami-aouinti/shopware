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
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\ShippingBundle\Config\Config;
use Pickware\ShippingBundle\Shipment\Model\ShipmentCollection;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResult;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResultSet;
use Pickware\SwissPostBundle\Api\Requests\CreateShipmentRequest;
use Pickware\SwissPostBundle\Api\SwissPostApiClientException;
use Pickware\SwissPostBundle\Api\SwissPostApiClientFactory;
use Pickware\SwissPostBundle\Config\SwissPostConfig;
use Shopware\Core\Framework\Context;

class SwissPostShipmentRegistrationService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly SwissPostApiClientFactory $swissPostApiClientFactory,
        private readonly SwissPostShipmentFactory $swissPostShipmentFactory,
        private readonly SwissPostResponseProcessor $swissPostResponseProcessor,
    ) {}

    public function registerSwissPostShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        $swissPostConfig = new SwissPostConfig($carrierConfig);
        $apiClient = $this->swissPostApiClientFactory->createSwissPostApiClient($swissPostConfig->getApiCredentials());

        $shipmentsOperationResultSet = new ShipmentsOperationResultSet();
        /** @var ShipmentCollection $shipments */
        $shipments = $this->entityManager->findBy(ShipmentDefinition::class, ['id' => $shipmentIds], $context);

        foreach ($shipments as $shipment) {
            $receiverAddress = $shipment->getShipmentBlueprint()->getReceiverAddress();
            $operationDescription = sprintf(
                'Create %s for shipment %s %s %s',
                $shipment->getIsReturnShipment() ? 'return labels' : 'labels',
                $shipment->getIsReturnShipment() ? 'from' : 'to',
                $receiverAddress->getFirstName(),
                $receiverAddress->getLastName(),
            );

            try {
                if ($shipment->getIsReturnShipment()) {
                    $swissPostShipments = $this->swissPostShipmentFactory->createSwissPostReturnShipmentsFromShipmentBlueprint(
                        $shipment->getShipmentBlueprint(),
                        $shipment->getId(),
                        $swissPostConfig,
                    );
                } else {
                    $swissPostShipments = $this->swissPostShipmentFactory->createSwissPostShipmentsFromShipmentBlueprint(
                        $shipment->getShipmentBlueprint(),
                        $shipment->getId(),
                        $swissPostConfig,
                    );
                }
            } catch (SwissPostAdapterException $exception) {
                $shipmentsOperationResultSet->addShipmentOperationResult(
                    ShipmentsOperationResult::createFailedOperationResult(
                        [$shipment->getId()],
                        $operationDescription,
                        [$exception->serializeToJsonApiError()],
                    ),
                );

                continue;
            }

            foreach ($swissPostShipments as $swissPostShipment) {
                try {
                    $shipmentCreationResponse = $apiClient->sendRequest(new CreateShipmentRequest($swissPostShipment));
                } catch (SwissPostApiClientException $exception) {
                    $shipmentsOperationResultSet->addShipmentOperationResult(
                        ShipmentsOperationResult::createFailedOperationResult(
                            [$shipment->getId()],
                            $operationDescription,
                            $exception->serializeToJsonApiErrors()->getErrors(),
                        ),
                    );

                    continue;
                }

                $this->swissPostResponseProcessor->processShipmentCreationResponse(
                    Json::decodeToObject((string) $shipmentCreationResponse->getBody()),
                    $shipmentsOperationResultSet,
                    $context,
                );
            }
        }

        return $shipmentsOperationResultSet;
    }
}
