<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery\Controller;

use Pickware\ApiVersioningBundle\Attributes\ApiLayer;
use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityResponseService;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailLoggingService;
use Pickware\PickwareErpStarter\PaperTrail\PaperTrailUriProvider;
use Pickware\PickwareErpStarter\Stock\ProductReservedStockUpdater;
use Pickware\PickwareWms\Acl\PickwareWmsFeaturePermissionsProvider;
use Pickware\PickwareWms\ApiVersion\ApiVersion20230721\ProductVariantApiLayer as ApiVersion20230721ProductVariantApiLayer;
use Pickware\PickwareWms\ApiVersion\ApiVersion20250207\DeliveryResponseForStockContainerCreationApiLayer as ApiVersion20250207DeliveryResponseApiLayer;
use Pickware\PickwareWms\ApiVersion\ApiVersion20250601\DeliveryStatusPackedApiLayer as ApiVersion20250601DeliveryStatusPackedApiLayer;
use Pickware\PickwareWms\ApiVersion\ApiVersion20250923\StockCancelledPickingProcessesApiLayer as ApiVersion20250923StockCancelledPickingProcessesApiLayer;
use Pickware\PickwareWms\Delivery\ApiVersioning\ApiVersion20240426\ShipmentBlueprintApiLayer as ApiVersion20240426ShipmentBlueprintApiLayer;
use Pickware\PickwareWms\Delivery\ApiVersioning\ApiVersion20240725\TrackingCodeApiLayer as ApiVersion20240725DeliveryTrackingCodeApiLayer;
use Pickware\PickwareWms\Delivery\ApiVersioning\ApiVersion20260122\TrackingCodeApiLayer as ApiVersion20260122DeliveryTrackingCodeApiLayer;
use Pickware\PickwareWms\Delivery\DeliveryParcelTrackingCodeMatchingService;
use Pickware\PickwareWms\Delivery\DeliveryService;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Pickware\PickwareWms\PaperTrail\WmsPaperTrailUri;
use Pickware\PickwareWms\PickingProcess\ApiVersioning\ApiVersion20240725\TrackingCodeApiLayer as ApiVersion20240725PickingProcessTrackingCodeApiLayer;
use Pickware\PickwareWms\PickingProcess\DeliveryStateMachine;
use Pickware\PickwareWms\PickingProcess\PickingProcessCreation;
use Pickware\PickwareWms\PickingProcess\PickingProcessException;
use Pickware\PickwareWms\PickingProcess\PickingProcessService;
use Pickware\PickwareWms\PickingProcess\StockReversionAction;
use Pickware\ShippingBundle\Shipment\Model\ShipmentDefinition;
use Pickware\ShippingBundle\Shipment\ShipmentBlueprint;
use Pickware\ShippingBundle\Shipment\ShipmentService;
use Pickware\ValidationBundle\Annotation\AclProtected;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Pickware\ValidationBundle\Annotation\JsonParameterAsArrayOfUuids;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Framework\Api\Response\ResponseFactoryRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class DeliveryController
{
    public function __construct(
        private readonly EntityResponseService $entityResponseService,
        private readonly DeliveryService $deliveryService,
        private readonly EntityManager $entityManager,
        private readonly ?ShipmentService $shipmentService,
        private readonly PickingProcessCreation $pickingProcessCreation,
        private readonly PickingProcessService $pickingProcessService,
        private readonly ResponseFactoryRegistry $responseFactoryRegistry,
        private readonly DeliveryParcelTrackingCodeMatchingService $deliveryParcelTrackingCodeMatchingService,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
        private readonly ProductReservedStockUpdater $productReservedStockUpdater,
        // Not available in older ERP versions
        private readonly ?PaperTrailUriProvider $paperTrailUriProvider = null,
        // Not available in older ERP versions
        private readonly ?PaperTrailLoggingService $paperTrailLoggingService = null,
    ) {}

    #[AclProtected(privilege: PickwareWmsFeaturePermissionsProvider::SPECIAL_BASIC_PRIVILEGE)]
    #[ApiLayer(ids: [
        ApiVersion20230721ProductVariantApiLayer::class,
        ApiVersion20240725DeliveryTrackingCodeApiLayer::class,
        ApiVersion20260122DeliveryTrackingCodeApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-create-order-documents-for-delivery.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/create-order-documents-for-delivery', methods: ['POST'])]
    public function createOrderDocumentsForDelivery(Request $request, Context $context): Response
    {
        $deliveryId = $request->get('deliveryId');

        /** @var DeliveryEntity $delivery */
        $delivery = $this->entityManager->findByPrimaryKey(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
            ['state'],
        );
        if (!$delivery) {
            return $this->createDeliveryNotFoundResponse($deliveryId);
        }

        if ($delivery->getState()->getTechnicalName() !== DeliveryStateMachine::STATE_DOCUMENTS_CREATED) {
            try {
                $this->deliveryService->completeDelivery($deliveryId, $context);

                $context->scope(
                    Context::SYSTEM_SCOPE,
                    fn() => $this->deliveryService->createOrderDocuments($deliveryId, $context),
                );
            } catch (PickingProcessException $exception) {
                return $exception
                    ->serializeToJsonApiErrors()
                    ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
        );
    }

    /**
     * @param array<string, mixed> $stockContainer
     */
    #[ApiLayer(ids: [
        ApiVersion20230721ProductVariantApiLayer::class,
        // At the time this API layer was created, the endpoint returned a picking process as response.
        ApiVersion20240725PickingProcessTrackingCodeApiLayer::class,
        ApiVersion20260122DeliveryTrackingCodeApiLayer::class,
        ApiVersion20250207DeliveryResponseApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-create-stock-container-for-delivery.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/create-stock-container-for-delivery', methods: ['PUT'])]
    public function createStockContainerForDelivery(
        #[JsonParameterAsUuid] string $deliveryId,
        #[JsonParameter] array $stockContainer,
        Context $context,
    ): Response {
        /** @var DeliveryEntity $delivery */
        $delivery = $this->entityManager->findByPrimaryKey(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
            ['pickingProcess'],
        );
        if (!$delivery) {
            return $this->createDeliveryNotFoundResponse($deliveryId);
        }

        $stockContainerId = $stockContainer['id'];
        if ($delivery->getStockContainerId() !== $stockContainerId) {
            // This is the idempotency check.
            try {
                $this->entityManager->runInTransactionWithRetry(
                    function() use ($stockContainerId, $context, $deliveryId, $delivery, $stockContainer): void {
                        $stockContainer['warehouseId'] = $delivery->getPickingProcess()->getWarehouseId();
                        $this->pickingProcessCreation->createStockContainer($stockContainer, $context);
                        $this->pickingProcessService->assignStockContainerToDelivery(
                            $deliveryId,
                            $stockContainerId,
                            $context,
                        );
                    },
                );
            } catch (PickingProcessException $exception) {
                return $exception
                    ->serializeToJsonApiErrors()
                    ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
        );
    }

    /**
     * @param string[] $deliveryIds
     */
    #[Route(path: '/api/_action/pickware-wms/skip-creating-order-documents-for-deliveries', methods: ['PUT'])]
    public function skipCreatingOrderDocumentsForDeliveries(#[JsonParameterAsArrayOfUuids] array $deliveryIds, Context $context): Response
    {
        $deliveries = $this->entityManager->findBy(
            DeliveryDefinition::class,
            ['id' => $deliveryIds],
            $context,
            ['state'],
        );

        if (count($deliveryIds) !== count($deliveries)) {
            return PickingProcessException::deliveriesNotFound(array_filter(
                $deliveryIds,
                fn(string $id) => !$deliveries->has($id),
            ))
                ->serializeToJsonApiErrors()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        $deliveriesInInvalidState = $deliveries->filter(
            fn(DeliveryEntity $delivery) => $delivery->getState()->getTechnicalName() !== DeliveryStateMachine::STATE_PICKED,
        );
        if ($deliveriesInInvalidState->count() > 0) {
            return PickingProcessException::deliveriesInInvalidStateForDocumentCreationSkipping(array_filter(
                $deliveryIds,
                fn(string $id) => $deliveriesInInvalidState->has($id),
            ))
                ->serializeToJsonApiErrors()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        foreach ($deliveryIds as $deliveryId) {
            try {
                $this->deliveryService->skipDocumentCreation($deliveryId, $context);
            } catch (PickingProcessException $exception) {
                return $exception
                    ->serializeToJsonApiErrors()
                    ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return new JsonResponse();
    }

    #[AclProtected(privilege: PickwareWmsFeaturePermissionsProvider::SPECIAL_BASIC_PRIVILEGE)]
    #[ApiLayer(ids: [
        ApiVersion20230721ProductVariantApiLayer::class,
        ApiVersion20240426ShipmentBlueprintApiLayer::class,
        ApiVersion20240725DeliveryTrackingCodeApiLayer::class,
        ApiVersion20260122DeliveryTrackingCodeApiLayer::class,
        ApiVersion20250601DeliveryStatusPackedApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-create-shipment-for-delivery.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/create-shipment-for-delivery', methods: ['POST'])]
    public function createShipmentForDelivery(Request $request, Context $context): Response
    {
        if (!$this->shipmentService) {
            // The shipping bundle is shipped as a dependency with this plugin, but it is not loaded as a bundle. So the
            // class exists either way, but the service only exist if the bundle is actually loaded via a real shipping
            // adapter.
            // See also this issue: https://github.com/pickware/shopware-plugins/issues/2755
            return PickingProcessException::noShippingCarrierInstalled()
                ->serializeToJsonApiErrors()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        $deliveryId = $request->get('deliveryId');

        /** @var DeliveryEntity $delivery */
        $delivery = $this->entityManager->findByPrimaryKey(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
        );
        if (!$delivery) {
            return $this->createDeliveryNotFoundResponse($deliveryId);
        }

        $shipmentId = $request->get('shipmentId');
        $existingShipment = $this->entityManager->findByPrimaryKey(
            ShipmentDefinition::class,
            $shipmentId,
            $context,
        );
        if (!$existingShipment) {
            // If a shipment with this ID already exist, we consider this action to be completed already do nothing.
            // This will make the controller idempotent.
            try {
                $context->scope(
                    Context::SYSTEM_SCOPE,
                    fn() => $this->deliveryService->createShipment(
                        $deliveryId,
                        $shipmentId,
                        ShipmentBlueprint::fromArray($request->get('shipmentBlueprint')),
                        $context,
                    ),
                );
            } catch (PickingProcessException $exception) {
                return $exception
                    ->serializeToJsonApiErrors()
                    ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
        );
    }

    #[JsonValidation(schemaFilePath: 'payload-ship-delivery.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/ship-delivery', methods: ['PUT'])]
    #[ApiLayer(ids: [
        ApiVersion20240725DeliveryTrackingCodeApiLayer::class,
        ApiVersion20260122DeliveryTrackingCodeApiLayer::class,
    ])]
    public function shipDelivery(Request $request, Context $context): Response
    {
        $requestPayload = $request->request->all();
        $deliveryId = $requestPayload['deliveryId'];

        $this->paperTrailUriProvider?->registerUri(WmsPaperTrailUri::withProcess('ship-delivery'));
        $this->paperTrailLoggingService?->logPaperTrailEvent(
            'Ship delivery',
            ['deliveryId' => $deliveryId],
        );

        try {
            $this->entityManager->runInTransactionWithRetry(
                function() use ($deliveryId, $context): void {
                    $this->productReservedStockUpdater->deferReservedStockCalculation(
                        function() use ($deliveryId, $context): void {
                            // We need a lock here otherwise the forthcoming select would produce phantom reads in the
                            // transaction of the ship-method.
                            $this->entityManager->lockPessimistically(
                                DeliveryDefinition::class,
                                ['id' => $deliveryId],
                                $context,
                            );

                            /** @var DeliveryEntity $delivery */
                            $delivery = $this->entityManager->findByPrimaryKey(
                                DeliveryDefinition::class,
                                $deliveryId,
                                $context,
                                ['state'],
                            );
                            if (!$delivery) {
                                throw PickingProcessException::deliveryNotFound($deliveryId);
                            }

                            if ($delivery->getState()->getTechnicalName() !== DeliveryStateMachine::STATE_SHIPPED) {
                                $this->deliveryService->ship($deliveryId, $context);
                            }
                        },
                        $context,
                    );
                },
            );
        } catch (PickingProcessException $exception) {
            return $exception
                ->serializeToJsonApiErrors()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        } finally {
            $this->paperTrailUriProvider?->reset();
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
        );
    }

    #[JsonValidation(schemaFilePath: 'payload-ship-deliveries-by-criteria.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/ship-deliveries-by-criteria', methods: ['PUT'])]
    public function shipDeliveriesByCriteria(Request $request, Context $context): Response
    {
        $this->paperTrailUriProvider?->registerUri(WmsPaperTrailUri::withProcess('ship-deliveries-by-criteria'));

        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $request->get('criteria'),
            DeliveryDefinition::class,
        );

        $sanitizedCriteria = Criteria::createFrom($criteria);
        $sanitizedCriteria->setLimit(null);
        $sanitizedCriteria->setOffset(null);

        $deliveryIds = $this->entityManager->findIdsBy(
            DeliveryDefinition::class,
            $sanitizedCriteria,
            $context,
        );

        if (count($deliveryIds) === 0) {
            return new JsonResponse();
        }

        $this->paperTrailLoggingService?->logPaperTrailEvent(
            'Ship deliveries by criteria',
            ['deliveryIds' => $deliveryIds],
        );

        $exceptions = new JsonApiErrors();

        $this->productReservedStockUpdater->deferReservedStockCalculation(
            function() use ($deliveryIds, $context, &$exceptions): void {
                foreach ($deliveryIds as $deliveryId) {
                    try {
                        $this->deliveryService->ship($deliveryId, $context);
                    } catch (PickingProcessException $exception) {
                        $exceptions->addErrors(...$exception->serializeToJsonApiErrors()->getErrors());

                        continue;
                    } catch (Throwable $exception) {
                        // We catch all other exceptions here because we want to continue with the next delivery.
                        // This is necessary because the ship method may throw exceptions that are not related to the
                        // picking process.
                        $exceptions->addError(new JsonApiError([
                            'code' => (string) $exception->getCode(),
                            'title' => 'Error while shipping delivery',
                            'detail' => $exception->getMessage(),
                            'meta' => [
                                'deliveryId' => $deliveryId,
                                'trace' => $exception->getTraceAsString(),
                            ],
                        ]));

                        continue;
                    }
                }
            },
            $context,
        );

        $this->paperTrailUriProvider?->reset();

        if ($exceptions->count() > 0) {
            return $exceptions->toJsonApiErrorResponse();
        }

        return new JsonResponse();
    }

    #[ApiLayer(ids: [
        ApiVersion20230721ProductVariantApiLayer::class,
        ApiVersion20240725DeliveryTrackingCodeApiLayer::class,
        ApiVersion20260122DeliveryTrackingCodeApiLayer::class,
        ApiVersion20250923StockCancelledPickingProcessesApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-cancel-delivery.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/cancel-delivery', methods: ['PUT'])]
    public function cancelDelivery(Request $request, Context $context): Response
    {
        $requestPayload = $request->request->all();
        $deliveryId = $requestPayload['deliveryId'];
        $stockReversionAction = StockReversionAction::from($requestPayload['stockReversionAction']);

        $this->paperTrailUriProvider?->registerUri(WmsPaperTrailUri::withProcess('cancel-delivery'));
        $this->paperTrailLoggingService?->logPaperTrailEvent(
            'Cancel delivery',
            ['deliveryId' => $deliveryId],
        );

        try {
            $this->entityManager->runInTransactionWithRetry(
                function() use ($deliveryId, $context, $stockReversionAction): void {
                    // We need a lock here otherwise the forthcoming select would produce phantom reads in the
                    // transaction of the ship-method.
                    $this->entityManager->lockPessimistically(
                        DeliveryDefinition::class,
                        ['id' => $deliveryId],
                        $context,
                    );

                    /** @var DeliveryEntity $delivery */
                    $delivery = $this->entityManager->findByPrimaryKey(
                        DeliveryDefinition::class,
                        $deliveryId,
                        $context,
                        ['state'],
                    );
                    if (!$delivery) {
                        throw PickingProcessException::deliveryNotFound($deliveryId);
                    }

                    if ($delivery->getState()->getTechnicalName() !== DeliveryStateMachine::STATE_CANCELLED) {
                        $this->deliveryService->cancel(
                            $deliveryId,
                            $context,
                            $stockReversionAction,
                        );
                    }
                },
            );
        } catch (PickingProcessException $exception) {
            return $exception
                ->serializeToJsonApiErrors()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        } finally {
            $this->paperTrailUriProvider?->reset();
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
        );
    }

    #[JsonValidation(schemaFilePath: 'payload-cancel-deliveries-by-criteria.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/cancel-deliveries-by-criteria', methods: ['PUT'])]
    public function cancelDeliveriesByCriteria(Request $request, Context $context): Response
    {
        $this->paperTrailUriProvider?->registerUri(WmsPaperTrailUri::withProcess('cancel-deliveries-by-criteria'));

        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $request->get('criteria'),
            DeliveryDefinition::class,
        );

        $sanitizedCriteria = Criteria::createFrom($criteria);
        $sanitizedCriteria->setLimit(null);
        $sanitizedCriteria->setOffset(null);

        $deliveryIds = $this->entityManager->findIdsBy(
            DeliveryDefinition::class,
            $sanitizedCriteria,
            $context,
        );

        if (count($deliveryIds) === 0) {
            return new JsonResponse();
        }

        $this->paperTrailLoggingService?->logPaperTrailEvent(
            'Cancel deliveries by criteria',
            ['deliveryIds' => $deliveryIds],
        );

        $exceptions = new JsonApiErrors();

        foreach ($deliveryIds as $deliveryId) {
            try {
                $this->deliveryService->cancel($deliveryId, $context, StockReversionAction::StockToUnknownLocation);
            } catch (PickingProcessException $exception) {
                $exceptions->addErrors(...$exception->serializeToJsonApiErrors()->getErrors());

                continue;
            }
        }

        $this->paperTrailUriProvider?->reset();

        if ($exceptions->count() > 0) {
            return $exceptions->toJsonApiErrorResponse();
        }

        return new JsonResponse();
    }

    #[ApiLayer(ids: [
        ApiVersion20230721ProductVariantApiLayer::class,
        ApiVersion20240725DeliveryTrackingCodeApiLayer::class,
        ApiVersion20260122DeliveryTrackingCodeApiLayer::class,
        ApiVersion20250601DeliveryStatusPackedApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-get-deliveries-matching-shipping-label-barcode-value.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/get-deliveries-matching-shipping-label-barcode-value', methods: ['PUT'])]
    public function getDeliveriesMatchingShippingLabelBarcodeValue(Request $request, Context $context): Response
    {
        $requestPayload = $request->request->all();

        $deliveryIds = $this->deliveryParcelTrackingCodeMatchingService->getIdsOfDeliveriesMatchingShippingLabelBarcodeValue(
            $requestPayload['shippingLabelBarcodeValue'],
        );

        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $requestPayload['criteria'],
            DeliveryDefinition::class,
        );
        $criteria->addFilter(new EqualsAnyFilter('id', $deliveryIds));

        // Use the repository instead of the entity manager search function because we need an entity search result for
        // the listing response
        $pickingProcessSearchResult = $this->entityManager
            ->getRepository(DeliveryDefinition::class)
            ->search($criteria, $context);

        return $this->responseFactoryRegistry
            ->getType($request)
            ->createListingResponse(
                $criteria,
                $pickingProcessSearchResult,
                $this->entityManager->getEntityDefinition(DeliveryDefinition::class),
                $request,
                $context,
            );
    }

    #[ApiLayer(ids: [
        ApiVersion20260122DeliveryTrackingCodeApiLayer::class,
    ])]
    #[Route(path: '/api/_action/pickware-wms/mark-delivery-as-packed', methods: ['PUT'])]
    public function markDeliveryAsPacked(
        #[JsonParameterAsUuid] string $deliveryId,
        Context $context,
    ): Response {
        try {
            $this->entityManager->runInTransactionWithRetry(function() use ($deliveryId, $context): void {
                $this->entityManager->lockPessimistically(
                    DeliveryDefinition::class,
                    ['id' => $deliveryId],
                    $context,
                );

                /** @var DeliveryEntity $delivery */
                $delivery = $this->entityManager->getByPrimaryKey(
                    DeliveryDefinition::class,
                    $deliveryId,
                    $context,
                    ['state'],
                );

                if ($delivery->getState()->getTechnicalName() !== DeliveryStateMachine::STATE_PACKED) {
                    $this->deliveryService->markAsPacked($deliveryId, $context);
                }
            });
        } catch (PickingProcessException $exception) {
            return $exception->serializeToJsonApiErrors()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
        );
    }

    #[ApiLayer(ids: [
        ApiVersion20260122DeliveryTrackingCodeApiLayer::class,
    ])]
    #[Route(path: '/api/_action/pickware-wms/complete-delivery', methods: ['PUT'])]
    public function completeDelivery(
        #[JsonParameterAsUuid] string $deliveryId,
        Context $context,
    ): Response {
        try {
            $this->entityManager->runInTransactionWithRetry(function() use ($deliveryId, $context): void {
                $this->entityManager->lockPessimistically(
                    DeliveryDefinition::class,
                    ['id' => $deliveryId],
                    $context,
                );

                /** @var DeliveryEntity $delivery */
                $delivery = $this->entityManager->getByPrimaryKey(
                    DeliveryDefinition::class,
                    $deliveryId,
                    $context,
                    ['state'],
                );

                if ($delivery->getState()->getTechnicalName() !== DeliveryStateMachine::STATE_PICKED) {
                    $this->deliveryService->completeDelivery($deliveryId, $context);
                }
            });
        } catch (PickingProcessException $exception) {
            return $exception->serializeToJsonApiErrors()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
        );
    }

    private function createDeliveryNotFoundResponse(string $deliveryId): Response
    {
        return PickingProcessException::deliveryNotFound($deliveryId)
            ->serializeToJsonApiErrors()
            ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
    }
}
