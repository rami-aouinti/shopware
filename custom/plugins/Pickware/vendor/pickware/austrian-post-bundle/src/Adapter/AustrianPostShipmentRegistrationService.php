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

use Pickware\AustrianPostBundle\Api\AustrianPostApiClientException;
use Pickware\AustrianPostBundle\Api\AustrianPostSoapApiClientFactory;
use Pickware\AustrianPostBundle\Api\Requests\AustrianPostRequestFactory;
use Pickware\AustrianPostBundle\Config\AustrianPostConfig;
use Pickware\DalBundle\EntityManager;
use Pickware\ShippingBundle\Config\Config;
use Pickware\ShippingBundle\Shipment\Model\ShipmentCollection;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResult;
use Pickware\ShippingBundle\Shipment\ShipmentsOperationResultSet;
use Shopware\Core\Framework\Context;

class AustrianPostShipmentRegistrationService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly AustrianPostShipmentFactory $shipmentFactory,
        private readonly AustrianPostSoapApiClientFactory $austrianPostApiClientFactory,
        private readonly AustrianPostResponseProcessor $austrianPostResponseProcessor,
    ) {}

    public function registerShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        $austrianPostConfig = new AustrianPostConfig($carrierConfig);
        $austrianPostShipmentServiceApiClient = $this->austrianPostApiClientFactory->createConfiguredAustrianPostShipmentServiceApiClient(
            $austrianPostConfig,
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
                    $austrianPostShipment = $this->shipmentFactory->createAustrianPostReturnShipmentFromShipmentBlueprint(
                        shipmentBlueprint: $shipment->getShipmentBlueprint(),
                    );
                } else {
                    $austrianPostShipment = $this->shipmentFactory->createAustrianPostShipmentFromShipmentBlueprint(
                        shipmentBlueprint: $shipment->getShipmentBlueprint(),
                        austrianPostConfig: $austrianPostConfig,
                    );
                }
            } catch (AustrianPostAdapterException $exception) {
                $shipmentsOperationResultSet->addShipmentOperationResult(
                    ShipmentsOperationResult::createFailedOperationResult(
                        [$shipment->getId()],
                        $operationDescription,
                        [$exception->serializeToJsonApiError()],
                    ),
                );

                continue;
            }

            $importShipmentRequest = AustrianPostRequestFactory::makeImportShipmentRequest(
                austrianPostShipment: $austrianPostShipment,
            );

            try {
                $response = $austrianPostShipmentServiceApiClient->sendRequest($importShipmentRequest);
            } catch (AustrianPostApiClientException $exception) {
                $shipmentsOperationResultSet->addShipmentOperationResult(
                    ShipmentsOperationResult::createFailedOperationResult(
                        [$shipment->getId()],
                        $operationDescription,
                        [$exception->serializeToJsonApiError()],
                    ),
                );

                continue;
            }

            $this->austrianPostResponseProcessor->processImportShipmentResponse(
                shipmentCreationResponse: $response,
                pageFormat: $austrianPostConfig->getLabelSize()->getPageFormat(),
                shipmentId: $shipment->getId(),
                context: $context,
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

    public function cancelShipments(
        array $shipmentIds,
        Config $carrierConfig,
        Context $context,
    ): ShipmentsOperationResultSet {
        $austrianPostConfig = new AustrianPostConfig($carrierConfig);
        $austrianPostShipmentServiceApiClient = $this->austrianPostApiClientFactory->createAuthenticatedAustrianPostShipmentServiceApiClient(
            $austrianPostConfig->getApiClientConfig(),
        );

        /** @var ShipmentCollection $shipments */
        $shipments = $this->entityManager->findBy(
            ShipmentDefinition::class,
            ['id' => $shipmentIds],
            $context,
            ['trackingCodes'],
        );

        $shipmentsOperationResultSet = new ShipmentsOperationResultSet();
        foreach ($shipments as $shipment) {
            $operationDescription = sprintf('Cancel shipment %s', $shipment->getId());

            try {
                $austrianPostShipmentServiceApiClient->sendRequest(
                    AustrianPostRequestFactory::makeCancelShipmentRequest(
                        trackingCodes: $shipment->getTrackingCodes(),
                    ),
                );
            } catch (AustrianPostApiClientException $exception) {
                $shipmentsOperationResultSet->addShipmentOperationResult(
                    ShipmentsOperationResult::createFailedOperationResult(
                        [$shipment->getId()],
                        $operationDescription,
                        [$exception->serializeToJsonApiError()],
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
