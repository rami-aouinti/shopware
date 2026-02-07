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
use Pickware\DhlExpressBundle\Api\DhlExpressApiClientException;
use Pickware\DhlExpressBundle\Api\DhlExpressApiClientFactory;
use Pickware\DhlExpressBundle\Api\Requests\CreateShipmentRequest;
use Pickware\DhlExpressBundle\Config\DhlExpressConfig;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\ShippingBundle\Config\Config;
use Pickware\ShippingBundle\Shipment\Model\ShipmentCollection;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResult;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResultSet;
use Shopware\Core\Framework\Context;

class DhlExpressShipmentRegistrationService
{
    private EntityManager $entityManager;
    private DhlExpressShipmentFactory $dhlExpressShipmentFactory;
    private DhlExpressResponseProcessor $dhlExpressResponseProcessor;
    private DhlExpressApiClientFactory $dhlExpressApiClientFactory;

    public function __construct(
        EntityManager $entityManager,
        DhlExpressApiClientFactory $dhlExpressApiClientFactory,
        DhlExpressShipmentFactory $dhlExpressShipmentFactory,
        DhlExpressResponseProcessor $dhlExpressResponseProcessor,
    ) {
        $this->entityManager = $entityManager;
        $this->dhlExpressApiClientFactory = $dhlExpressApiClientFactory;
        $this->dhlExpressShipmentFactory = $dhlExpressShipmentFactory;
        $this->dhlExpressResponseProcessor = $dhlExpressResponseProcessor;
    }

    public function registerDhlExpressShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        $dhlExpressConfig = new DhlExpressConfig($carrierConfig);
        $apiClient = $this->dhlExpressApiClientFactory->createDhlExpressApiClient(
            $dhlExpressConfig->getApiCredentials(),
        );

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
                    $dhlExpressShipment = $this->dhlExpressShipmentFactory
                        ->createDhlExpressReturnShipmentFromShipmentBlueprint(
                            $shipment->getShipmentBlueprint(),
                            $dhlExpressConfig,
                        );
                } else {
                    $dhlExpressShipment = $this->dhlExpressShipmentFactory
                        ->createDhlExpressShipmentFromShipmentBlueprint(
                            $shipment->getShipmentBlueprint(),
                            $dhlExpressConfig,
                        );
                }

                $createShipmentRequest = new CreateShipmentRequest($dhlExpressShipment);
            } catch (DhlExpressAdapterException $exception) {
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
            } catch (DhlExpressApiClientException $exception) {
                $shipmentsOperationResultSet->addShipmentOperationResult(
                    ShipmentsOperationResult::createFailedOperationResult(
                        [$shipment->getId()],
                        $operationDescription,
                        [$exception->serializeToJsonApiError()],
                    ),
                );

                continue;
            }

            $this->dhlExpressResponseProcessor->processShipmentCreationResponse(
                Json::decodeToObject((string) $shipmentCreationResponse->getBody()),
                $shipment->getId(),
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

    public function cancelDhlExpressShipments(
        array $shipmentIds,
        Context $context,
    ): ShipmentsOperationResultSet {
        /** @var ShipmentCollection $shipments */
        $shipments = $this->entityManager->findBy(
            ShipmentDefinition::class,
            ['id' => $shipmentIds],
            $context,
        );

        $shipmentsOperationResultSet = new ShipmentsOperationResultSet();
        foreach ($shipments as $shipment) {
            $operationDescription = sprintf(
                'Cancel shipment %s',
                $shipment->getId(),
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
}
