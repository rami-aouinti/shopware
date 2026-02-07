<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Shipment\Controller;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\ApiVersioningBundle\Attributes\ApiLayer;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\ResponseFactory;
use Pickware\ShippingBundle\Carrier\CarrierAdapterException;
use Pickware\ShippingBundle\Config\ConfigException;
use Pickware\ShippingBundle\Notifications\NotificationService;
use Pickware\ShippingBundle\Shipment\ApiVersioning\ApiVersion20240426\ShipmentBlueprintApiLayer as ApiVersion20240426ShipmentBlueprintApiLayer;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\Model\ShipmentEntity;
use Pickware\ShippingBundle\Shipment\ShipmentBlueprint;
use Pickware\ShippingBundle\Shipment\ShipmentBlueprintCreationConfiguration;
use Pickware\ShippingBundle\Shipment\ShipmentException;
use Pickware\ShippingBundle\Shipment\ShipmentService;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class ShipmentController
{
    public function __construct(
        private readonly ShipmentService $shipmentService,
        private readonly NotificationService $notificationService,
        private readonly EntityManager $entityManager,
    ) {}

    #[ApiLayer(ids: [ApiVersion20240426ShipmentBlueprintApiLayer::class])]
    #[JsonValidation(schemaFilePath: 'create-shipment-blueprint-for-order-payload.schema.json')]
    #[Route(
        path: '/api/_action/pickware-shipping/shipment/create-shipment-blueprint-for-order',
        name: 'api.action.pickware-shipping.shipment.create-shipment-blueprint-for-order',
        methods: ['POST'],
    )]
    public function createShipmentBlueprintForOrder(Request $request, Context $context): Response
    {
        $requestPayload = $request->request->all();
        $orderId = $requestPayload['orderId'];
        $productsInParcel = $requestPayload['productsInParcel'] ?? null;
        $shipmentBlueprintCreationConfiguration = ShipmentBlueprintCreationConfiguration::fromArray(
            $requestPayload['configuration'] ?? [],
        );

        $notifications = $this->notificationService->collectNotificationsInCallback(
            function() use (
                $context,
                $orderId,
                $productsInParcel,
                $shipmentBlueprintCreationConfiguration,
                &$result,
            ): void {
                $result = $this->shipmentService->createShipmentBlueprintForOrder(
                    orderId: $orderId,
                    shipmentBlueprintCreationConfiguration: $shipmentBlueprintCreationConfiguration,
                    productsInParcel: $productsInParcel,
                    context: $context,
                );
            },
        );

        return new JsonResponse([
            'shipmentBlueprint' => $result->shipmentBlueprint,
            'notifications' => $notifications,
            'removedFields' => $result->removedFields,
        ]);
    }

    #[JsonValidation(schemaFilePath: 'create-shipment-blueprints-for-orders-payload.schema.json')]
    #[Route(
        path: '/api/_action/pickware-shipping/shipment/create-shipment-blueprints-for-orders',
        name: 'api.action.pickware-shipping.shipment.create-shipment-blueprints-for-orders',
        methods: ['POST'],
    )]
    public function createShipmentBlueprintsForOrders(Request $request, Context $context): Response
    {
        $orderIds = $request->get('orderIds');

        $notifications = $this->notificationService->collectNotificationsInCallback(
            function() use ($context, $orderIds, &$result): void {
                $shipmentBlueprintCreationConfigurationByOrderId = [];
                $productsInParcelByOrderId = [];
                foreach ($orderIds as $orderId) {
                    $shipmentBlueprintCreationConfigurationByOrderId[$orderId] = ShipmentBlueprintCreationConfiguration::makeDefault();
                    $productsInParcelByOrderId[$orderId] = null; // Not implemented yet, can be added if needed.
                }
                $result = $this->shipmentService->createShipmentBlueprintsForOrders(
                    $shipmentBlueprintCreationConfigurationByOrderId,
                    $productsInParcelByOrderId,
                    $context,
                );
            },
        );

        return new JsonResponse([
            'shipmentBlueprintsWithOrderId' => $result,
            'notifications' => $notifications,
        ]);
    }

    #[JsonValidation(schemaFilePath: 'create-shipment-for-order-payload.schema.json')]
    #[Route(
        path: '/api/_action/pickware-shipping/shipment/create-shipment-for-order',
        name: 'api.action.pickware-shipping.shipment.create-shipment-for-order',
        methods: ['POST'],
    )]
    public function createShipmentForOrder(Request $request, Context $context): Response
    {
        $orderId = $request->request->getAlnum('orderId');
        $shipmentBlueprintArray = $request->get('shipmentBlueprint');

        $shipmentBlueprint = ShipmentBlueprint::fromArray($shipmentBlueprintArray);
        try {
            $result = $this->shipmentService->createShipmentForOrder($shipmentBlueprint, $orderId, $context);
        } catch (ConfigException | CarrierAdapterException | ShipmentException $exception) {
            return $exception
                ->serializeToJsonApiError()
                ->setStatus(Response::HTTP_BAD_REQUEST)
                ->toJsonApiErrorResponse();
        }

        return new JsonResponse($result);
    }

    #[JsonValidation(schemaFilePath: 'create-shipments-for-orders-payload.schema.json')]
    #[Route(
        path: '/api/_action/pickware-shipping/shipment/create-shipments-for-orders',
        name: 'api.action.pickware-shipping.shipment.create-shipments-for-orders',
        methods: ['POST'],
    )]
    public function createShipmentsForOrders(Request $request, Context $context): Response
    {
        $shipmentBlueprintsWithOrderIdArrays = $request->get('shipmentBlueprintsWithOrderId');

        $shipmentPayloads = [];
        foreach ($shipmentBlueprintsWithOrderIdArrays as $shipmentBlueprintWithOrderArray) {
            $shipmentPayloads[] = [
                'orders' => [
                    ['id' => $shipmentBlueprintWithOrderArray['orderId']],
                ],
                'shipmentBlueprint' => ShipmentBlueprint::fromArray($shipmentBlueprintWithOrderArray['shipmentBlueprint']),
            ];
        }

        try {
            $result = $this->shipmentService->createShipmentsForOrders($shipmentPayloads, $context);
        } catch (ConfigException | CarrierAdapterException $exception) {
            return $exception
                ->serializeToJsonApiError()
                ->setStatus(Response::HTTP_BAD_REQUEST)
                ->toJsonApiErrorResponse();
        }

        return new JsonResponse($result);
    }

    /**
     * @deprecated tag:next-major The route pickware-shipping-shipment/{shipmentId}/aggregated-tracking-urls is deprecated and will be removed. Use _action/pickware-shipping/shipment/{shipmentId}/aggregated-tracking-urls instead.
     */
    #[Route(
        path: '/api/_action/pickware-shipping/shipment/{shipmentId}/aggregated-tracking-urls',
        name: 'api.action.pickware-shipping.shipment.aggregated-tracking-urls',
        requirements: ['shipmentId' => '[a-fA-F0-9]{32}'],
        methods: ['GET'],
    )]
    #[Route(
        path: '/api/pickware-shipping-shipment/{shipmentId}/aggregated-tracking-urls',
        name: 'api.pickware_shipping_shipment.aggregated-tracking-urls',
        requirements: ['shipmentId' => '[a-fA-F0-9]{32}'],
        methods: ['GET'],
    )]
    public function shipmentAggregatedTrackingUrls(string $shipmentId, Context $context): JsonResponse
    {
        $urls = $this->shipmentService->getTrackingUrlsForShipment($shipmentId, $context);

        return new JsonResponse($urls);
    }

    #[Route(
        path: '/api/_action/pickware-shipping/shipment/cancel-shipment',
        name: 'api.action.pickware-shipping-shipment.cancel',
        methods: ['POST'],
    )]
    public function cancelShipment(Request $request, Context $context): JsonResponse
    {
        $shipmentId = $request->request->getAlnum('shipmentId');
        if (!$shipmentId) {
            return ResponseFactory::createParameterMissingResponse('shipmentId');
        }

        /** @var ShipmentEntity $shipment */
        $shipment = $this->entityManager->findByPrimaryKey(ShipmentDefinition::class, $shipmentId, $context, [
            'carrier',
            'salesChannel',
        ]);

        if ($shipment === null) {
            return (new LocalizableJsonApiError([
                'status' => Response::HTTP_BAD_REQUEST,
                'title' => [
                    'de' => 'Sendung nicht gefunden',
                    'en' => 'Shipment not found',
                ],
                'detail' => [
                    'de' => 'Die angegebene Sendung existiert nicht.',
                    'en' => 'The specified shipment does not exist.',
                ],
            ]))->toJsonApiErrorResponse();
        }

        $result = $this->shipmentService->cancelShipment($shipmentId, $context);

        return new JsonResponse($result);
    }

    #[JsonValidation(schemaFilePath: 'create-shipment-blueprint-for-order-payload.schema.json')]
    #[Route(
        path: '/api/_action/pickware-shipping/shipment/create-return-shipment-blueprint-for-order',
        name: 'api.action.pickware-shipping.shipment.create-return-shipment-blueprint-for-order',
        methods: ['POST'],
    )]
    public function createReturnShipmentBlueprintForOrder(Request $request, Context $context): Response
    {
        $orderId = $request->request->getAlnum('orderId');
        $shipmentBlueprintCreationConfigurationParameter = $request->request->all('configuration') ?? null;
        $shipmentBlueprintCreationConfiguration = ShipmentBlueprintCreationConfiguration::fromArray(
            $shipmentBlueprintCreationConfigurationParameter ?? [],
        );

        $notifications = $this->notificationService->collectNotificationsInCallback(
            function() use ($context, $orderId, $shipmentBlueprintCreationConfiguration, &$result): void {
                $result = $this->shipmentService->createReturnShipmentBlueprintForOrder(
                    $orderId,
                    $shipmentBlueprintCreationConfiguration,
                    $context,
                );
            },
        );

        return new JsonResponse([
            'shipmentBlueprint' => $result->shipmentBlueprint,
            'notifications' => $notifications,
            'removedFields' => $result->removedFields,
        ]);
    }

    #[JsonValidation(schemaFilePath: 'create-shipment-for-order-payload.schema.json')]
    #[Route(
        path: '/api/_action/pickware-shipping/shipment/create-return-shipment-for-order',
        name: 'api.action.pickware-shipping.shipment.create-return-shipment-for-order',
        methods: ['POST'],
    )]
    public function createReturnShipmentForOrder(Request $request, Context $context): Response
    {
        $orderId = $request->request->getAlnum('orderId');
        $returnShipmentBlueprintArray = $request->get('shipmentBlueprint');

        $returnShipmentBlueprint = ShipmentBlueprint::fromArray($returnShipmentBlueprintArray);
        try {
            $result = $this->shipmentService->createReturnShipmentForOrder($returnShipmentBlueprint, $orderId, $context);
        } catch (ConfigException | CarrierAdapterException | ShipmentException $exception) {
            return $exception
                ->serializeToJsonApiError()
                ->setStatus(Response::HTTP_BAD_REQUEST)
                ->toJsonApiErrorResponse();
        }

        return new JsonResponse($result);
    }
}
