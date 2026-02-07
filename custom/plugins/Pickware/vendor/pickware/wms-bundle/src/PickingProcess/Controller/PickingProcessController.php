<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess\Controller;

use Pickware\ApiVersioningBundle\Attributes\ApiLayer;
use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityResponseService;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\LockingBundle\Lock\LockHandler;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyRecordValue;
use Pickware\PickwareErpStarter\Stock\Model\StockMovementDefinition;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareWms\ApiVersion\ApiVersion20230721\ProductVariantApiLayer as ApiVersion20230721ProductVariantApiLayer;
use Pickware\PickwareWms\ApiVersion\ApiVersion20250923\StockCancelledPickingProcessesApiLayer as ApiVersion20250923StockCancelledPickingProcessesApiLayer;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Pickware\PickwareWms\Device\Device;
use Pickware\PickwareWms\PickingProcess\ApiVersioning\ApiVersion20240712\PickingProfileApiLayer as ApiVersion20240721PickingProfileApiLayer;
use Pickware\PickwareWms\PickingProcess\ApiVersioning\ApiVersion20240725\TrackingCodeApiLayer as ApiVersion20240725TrackingCodeApiLayer;
use Pickware\PickwareWms\PickingProcess\ApiVersioning\ApiVersion20250703\OrderSelectionCriteriaDeliveryFilterApiLayer as ApiVersion20250703OrderSelectionCriteriaDeliveryFilterApiLayer;
use Pickware\PickwareWms\PickingProcess\ApiVersioning\ApiVersion20251111\SingleItemOrdersPickingModeApiLayer as ApiVersion20251111SingleItemOrdersPickingModeApiLayer;
use Pickware\PickwareWms\PickingProcess\ApiVersioning\ApiVersion20260122\TrackingCodeApiLayer as ApiVersion20260122TrackingCodeApiLayer;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessEntity;
use Pickware\PickwareWms\PickingProcess\OrderAlreadyContainedInPickingProcessException;
use Pickware\PickwareWms\PickingProcess\PickableOrderService;
use Pickware\PickwareWms\PickingProcess\PickingItem;
use Pickware\PickwareWms\PickingProcess\PickingProcessCreation;
use Pickware\PickwareWms\PickingProcess\PickingProcessCreationLockId;
use Pickware\PickwareWms\PickingProcess\PickingProcessException;
use Pickware\PickwareWms\PickingProcess\PickingProcessReceiptContentGenerator;
use Pickware\PickwareWms\PickingProcess\PickingProcessReceiptDocumentGenerator;
use Pickware\PickwareWms\PickingProcess\PickingProcessService;
use Pickware\PickwareWms\PickingProcess\PickingProcessStateMachine;
use Pickware\PickwareWms\PickingProcess\StockReversionAction;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileDefinition;
use Pickware\ShopwareExtensionsBundle\GeneratedDocument\GeneratedDocumentExtension as DocumentBundleResponseFactory;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class PickingProcessController
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly PickingProcessService $pickingProcessService,
        private readonly EntityResponseService $entityResponseService,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
        private readonly PickingProcessCreation $pickingProcessCreation,
        private readonly PickableOrderService $pickableOrderService,
        #[Autowire(service: 'pickware_wms.picking_process_creation_lock_handler')]
        private readonly LockHandler $lockHandler,
        private readonly PickingProcessReceiptContentGenerator $pickingProcessReceiptContentGenerator,
        private readonly PickingProcessReceiptDocumentGenerator $pickingProcessReceiptDocumentGenerator,
    ) {}

    #[ApiLayer(ids: [
        ApiVersion20230721ProductVariantApiLayer::class,
        ApiVersion20240721PickingProfileApiLayer::class,
        ApiVersion20240725TrackingCodeApiLayer::class,
        ApiVersion20260122TrackingCodeApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-create-and-start-picking-process.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/create-and-start-picking-process', methods: ['PUT'])]
    public function createAndStartPickingProcess(Request $request, Context $context): Response
    {
        $requestPayload = $request->request->all();
        $pickingProcessPayload = $requestPayload['pickingProcess'];
        $pickingProfileId = $requestPayload['pickingProfileId'];

        if (!$this->doesPickingProfileExist($pickingProfileId, $context)) {
            return self::createPickingProfileNotFoundResponse();
        }

        $pickingProcess = $this->entityManager->findByPrimaryKey(
            PickingProcessDefinition::class,
            $pickingProcessPayload['id'],
            $context,
        );
        if (!$pickingProcess) {
            // This is an idempotency check. If a picking process with the same ID already exist we assume this action
            // has been executed already and just return the picking process.
            try {
                $this->lockHandler->lockPessimistically(
                    lockIdProvider: new PickingProcessCreationLockId(),
                    callback: function() use ($context, $pickingProfileId, $pickingProcessPayload): void {
                        $this->entityManager->runInTransactionWithRetry(
                            function() use ($context, $pickingProfileId, $pickingProcessPayload): void {
                                $this->pickingProcessCreation->createPickingProcess(
                                    $pickingProcessPayload,
                                    $pickingProfileId,
                                    $context,
                                );
                                $this->pickingProcessService->startOrContinue(
                                    pickingProcessId: $pickingProcessPayload['id'],
                                    pickingProfileId: $pickingProfileId,
                                    context: $context,
                                );
                            },
                        );
                    },
                );
            } catch (PickingProcessException $pickingProcessException) {
                return $pickingProcessException
                    ->serializeToJsonApiErrors()
                    ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            PickingProcessDefinition::class,
            $pickingProcessPayload['id'],
            $context,
        );
    }

    #[ApiLayer(ids: [
        ApiVersion20230721ProductVariantApiLayer::class,
        ApiVersion20240721PickingProfileApiLayer::class,
        ApiVersion20240725TrackingCodeApiLayer::class,
        ApiVersion20260122TrackingCodeApiLayer::class,
        ApiVersion20250703OrderSelectionCriteriaDeliveryFilterApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-create-and-start-picking-process-for-next-orders.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/create-and-start-picking-process-for-next-orders', methods: ['PUT'])]
    public function createAndStartPickingProcessForNextOrders(Request $request, Context $context): Response
    {
        $requestPayload = $request->request->all();
        $pickingProcessPayload = $requestPayload['pickingProcess'];
        $pickingProfileId = $requestPayload['pickingProfileId'];

        if (!$this->doesPickingProfileExist($pickingProfileId, $context)) {
            return self::createPickingProfileNotFoundResponse();
        }

        $pickingProcess = $this->entityManager->findByPrimaryKey(
            PickingProcessDefinition::class,
            $pickingProcessPayload['id'],
            $context,
        );
        if (!$pickingProcess) {
            // This is an idempotency check. If a picking process with the same ID already exist we assume this action
            // has been executed already and just return the picking process.
            $orderSelectionCriteria = $this->criteriaJsonSerializer->deserializeFromArray(
                $requestPayload['orderSelectionCriteria'],
                OrderDefinition::class,
            );

            try {
                $this->lockHandler->lockPessimistically(
                    lockIdProvider: new PickingProcessCreationLockId(),
                    callback: function() use ($orderSelectionCriteria, $pickingProfileId, $pickingProcessPayload, $context): void {
                        $this->entityManager->runInTransactionWithRetry(
                            function() use ($orderSelectionCriteria, $pickingProfileId, $pickingProcessPayload, $context): void {
                                $deliveryPayloads = $pickingProcessPayload['deliveries'];
                                if ($pickingProcessPayload['pickingMode'] == PickingProcessDefinition::PICKING_MODE_SINGLE_ITEM_ORDERS_PICKING) {
                                    $orderIds = $this->pickableOrderService->findNextPickableOrderIdsForSingleItemPicking(
                                        orderCriteria: $orderSelectionCriteria,
                                        targetCount: count($deliveryPayloads),
                                        context: $context,
                                    );
                                } else {
                                    $orderIds = $this->pickableOrderService->findNextPickableOrderIds(
                                        orderCriteria: $orderSelectionCriteria,
                                        targetCount: count($deliveryPayloads),
                                        context: $context,
                                    );
                                }

                                if (count($orderIds) === 0) {
                                    throw PickingProcessException::noOrderFound();
                                }

                                $deliveryPayloads = array_slice($deliveryPayloads, 0, count($orderIds));

                                $pickingProcessId = $pickingProcessPayload['id'];
                                for ($i = 0; $i < count($orderIds); $i++) {
                                    $deliveryPayloads[$i]['orderId'] = $orderIds[$i];
                                    $deliveryPayloads[$i]['pickingProcessId'] = $pickingProcessId;
                                }

                                $pickingProcessPayload['deliveries'] = $deliveryPayloads;
                                $this->pickingProcessCreation->createPickingProcess(
                                    $pickingProcessPayload,
                                    $pickingProfileId,
                                    $context,
                                );
                                $this->pickingProcessService->startOrContinue(
                                    pickingProcessId: $pickingProcessId,
                                    pickingProfileId: $pickingProfileId,
                                    context: $context,
                                );
                            },
                        );
                    },
                );
            } catch (OrderAlreadyContainedInPickingProcessException $e) {
                // Since we are explicitly selecting orders that are not part of a picking process yet, this exception
                // should never be thrown. If it is, it indicates a bug in the order selection logic and therefore
                // should be treated as an internal server error.
                throw $e;
            } catch (PickingProcessException $pickingProcessException) {
                return $pickingProcessException
                    ->serializeToJsonApiErrors()
                    ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            PickingProcessDefinition::class,
            $pickingProcessPayload['id'],
            $context,
        );
    }

    #[ApiLayer(ids: [
        ApiVersion20230721ProductVariantApiLayer::class,
        ApiVersion20240725TrackingCodeApiLayer::class,
        ApiVersion20260122TrackingCodeApiLayer::class,
        ApiVersion20251111SingleItemOrdersPickingModeApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-pick-item-into-delivery.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/pick-item-into-delivery', methods: ['PUT'])]
    public function pickItemIntoDelivery(
        #[JsonParameterAsUuid] string $deliveryId,
        #[JsonParameterAsUuid] string $productId,
        #[JsonParameterAsUuid] ?string $batchId,
        #[JsonParameterAsUuid] string $stockMovementId,
        #[JsonParameter] int $quantity,
        #[JsonParameter] array $stockLocation,
        #[JsonParameter] ?array $pickingPropertyRecords,
        Context $context,
    ): Response {
        /** @var DeliveryEntity $delivery */
        $delivery = $this->entityManager->findByPrimaryKey(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
        );
        if (!$delivery) {
            return $this->createDeliveryNotFoundResponse($deliveryId);
        }

        $stockMovement = $this->entityManager->findByPrimaryKey(
            StockMovementDefinition::class,
            $stockMovementId,
            $context,
        );
        if (!$stockMovement) {
            try {
                $pickingItem = new PickingItem(
                    stockMovementId: $stockMovementId,
                    source: StockLocationReference::create($stockLocation),
                    productId: $productId,
                    batchId: $batchId,
                    quantity: $quantity,
                    pickingPropertyRecords: array_map(
                        fn(array $record) => array_map(
                            fn(array $recordValue) => new PickingPropertyRecordValue(
                                $recordValue['name'],
                                $recordValue['value'],
                            ),
                            $record,
                        ),
                        $pickingPropertyRecords ?? [],
                    ),
                );
                $this->pickingProcessService->pickItemIntoDelivery($deliveryId, $pickingItem, $context);
            } catch (WriteTypeIntendException $exception) {
                if (($exception->getParameters()['definition'] ?? null) === StockMovementDefinition::ENTITY_NAME) {
                    return $this->entityResponseService->makeEntityDetailResponse(
                        PickingProcessDefinition::class,
                        $delivery->getPickingProcessId(),
                        $context,
                    );
                }

                throw $exception;
            } catch (PickingProcessException $exception) {
                return $exception
                    ->serializeToJsonApiErrors()
                    ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            PickingProcessDefinition::class,
            $delivery->getPickingProcessId(),
            $context,
        );
    }

    #[ApiLayer(ids: [
        ApiVersion20230721ProductVariantApiLayer::class,
        ApiVersion20240725TrackingCodeApiLayer::class,
        ApiVersion20260122TrackingCodeApiLayer::class,
        ApiVersion20251111SingleItemOrdersPickingModeApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-pick-item-into-picking-process.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/pick-item-into-picking-process', methods: ['PUT'])]
    public function pickItemIntoPickingProcess(Request $request, Context $context): Response
    {
        $pickingProcessId = $request->get('pickingProcessId');
        $stockMovementId = $request->get('stockMovementId');

        /** @var PickingProcessEntity $pickingProcess */
        $pickingProcess = $this->entityManager->findByPrimaryKey(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
        );
        if (!$pickingProcess) {
            return $this->createPickingProcessNotFoundBadRequestErrorResponse($pickingProcessId);
        }

        $stockMovement = $this->entityManager->findByPrimaryKey(
            StockMovementDefinition::class,
            $stockMovementId,
            $context,
        );
        if (!$stockMovement) {
            try {
                $item = new PickingItem(
                    stockMovementId: $stockMovementId,
                    source: StockLocationReference::create($request->get('stockLocation')),
                    productId: $request->get('productId'),
                    batchId: $request->get('batchId'),
                    quantity: $request->get('quantity'),
                    pickingPropertyRecords: [],
                );
                $this->pickingProcessService->pickItemIntoPickingProcess($pickingProcessId, $item, $context);
            } catch (WriteTypeIntendException $exception) {
                if (($exception->getParameters()['definition'] ?? null) === StockMovementDefinition::ENTITY_NAME) {
                    return $this->entityResponseService->makeEntityDetailResponse(
                        PickingProcessDefinition::class,
                        $pickingProcessId,
                        $context,
                    );
                }

                throw $exception;
            } catch (PickingProcessException $exception) {
                return $exception
                    ->serializeToJsonApiErrors()
                    ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
        );
    }

    #[ApiLayer(ids: [
        ApiVersion20230721ProductVariantApiLayer::class,
        ApiVersion20240725TrackingCodeApiLayer::class,
        ApiVersion20260122TrackingCodeApiLayer::class,
        ApiVersion20251111SingleItemOrdersPickingModeApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-take-over-picking-process.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/take-over-picking-process', methods: ['PUT'])]
    public function takeOverPickingProcess(Request $request, Context $context): Response
    {
        $requestPayload = $request->request->all();
        $pickingProcessId = $requestPayload['pickingProcessId'];
        // Old versions of the App do not send the `warehouseId` and `pickingProfileId` parameter
        $warehouseId = $requestPayload['warehouseId'] ?? null;
        $pickingProfileId = $requestPayload['pickingProfileId'] ?? null;
        /** @var PickingProcessEntity $pickingProcess */
        $pickingProcess = $this->entityManager->findByPrimaryKey(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
        );
        if (!$pickingProcess) {
            return $this->createPickingProcessNotFoundBadRequestErrorResponse($pickingProcessId);
        }

        try {
            $this->entityManager->runInTransactionWithRetry(function() use ($context, $pickingProcessId, $pickingProfileId, $warehouseId): void {
                if ($warehouseId !== null) {
                    $this->pickingProcessService->moveIntoWarehouse($pickingProcessId, $warehouseId, $context);
                }
                $this->pickingProcessService->takeOver($pickingProcessId, $pickingProfileId, $context);
            });
        } catch (PickingProcessException $exception) {
            return $exception
                ->serializeToJsonApiErrors()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
        );
    }

    #[ApiLayer(ids: [
        ApiVersion20230721ProductVariantApiLayer::class,
        ApiVersion20240725TrackingCodeApiLayer::class,
        ApiVersion20260122TrackingCodeApiLayer::class,
        ApiVersion20251111SingleItemOrdersPickingModeApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-complete-picking-process.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/complete-picking-process', methods: ['PUT'])]
    public function completePickingProcess(Request $request, Context $context): Response
    {
        $requestPayload = $request->request->all();
        $pickingProcessId = $requestPayload['pickingProcessId'];

        try {
            $this->entityManager->runInTransactionWithRetry(
                function() use ($pickingProcessId, $context): void {
                    // We need a lock here otherwise the forthcoming select would produce phantom reads in the
                    // transaction of the complete-method.
                    $this->entityManager->lockPessimistically(
                        PickingProcessDefinition::class,
                        ['id' => $pickingProcessId],
                        $context,
                    );

                    /** @var PickingProcessEntity $pickingProcess */
                    $pickingProcess = $this->entityManager->findByPrimaryKey(
                        PickingProcessDefinition::class,
                        $pickingProcessId,
                        $context,
                        ['state'],
                    );
                    if (!$pickingProcess) {
                        throw PickingProcessException::pickingProcessNotFound($pickingProcessId);
                    }

                    if ($pickingProcess->getState()->getTechnicalName() !== PickingProcessStateMachine::STATE_PICKED) {
                        $this->pickingProcessService->complete($pickingProcessId, $context);
                    }
                },
            );
        } catch (PickingProcessException $exception) {
            return $exception
                ->serializeToJsonApiErrors()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
        );
    }

    #[ApiLayer(ids: [
        ApiVersion20260122TrackingCodeApiLayer::class,
    ])]
    #[Route(path: '/api/_action/pickware-wms/complete-pre-collecting', methods: ['PUT'])]
    public function completePreCollecting(
        #[JsonParameterAsUuid] string $pickingProcessId,
        Context $context,
    ): Response {
        try {
            $this->entityManager->runInTransactionWithRetry(
                function() use ($pickingProcessId, $context): void {
                    // We need a lock here otherwise the forthcoming select would produce phantom reads in the
                    // transaction of the complete-pre-collecting-method.
                    $this->entityManager->lockPessimistically(
                        PickingProcessDefinition::class,
                        ['id' => $pickingProcessId],
                        $context,
                    );

                    /** @var PickingProcessEntity $pickingProcess */
                    $pickingProcess = $this->entityManager->findByPrimaryKey(
                        PickingProcessDefinition::class,
                        $pickingProcessId,
                        $context,
                        ['state'],
                    );
                    if (!$pickingProcess) {
                        throw PickingProcessException::pickingProcessNotFound($pickingProcessId);
                    }

                    if ($pickingProcess->getState()->getTechnicalName() !== PickingProcessStateMachine::STATE_PICKED) {
                        $this->pickingProcessService->completePreCollecting($pickingProcessId, $context);
                    }
                },
            );
        } catch (PickingProcessException $exception) {
            return $exception
                ->serializeToJsonApiErrors()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
        );
    }

    #[ApiLayer(ids: [
        ApiVersion20230721ProductVariantApiLayer::class,
        ApiVersion20240725TrackingCodeApiLayer::class,
        ApiVersion20260122TrackingCodeApiLayer::class,
        ApiVersion20250923StockCancelledPickingProcessesApiLayer::class,
        ApiVersion20251111SingleItemOrdersPickingModeApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-cancel-picking-process.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/cancel-picking-process', methods: ['PUT'])]
    public function cancelPickingProcess(Request $request, Context $context): Response
    {
        $requestPayload = $request->request->all();
        $pickingProcessId = $requestPayload['pickingProcessId'];
        $stockReversionAction = StockReversionAction::from($requestPayload['stockReversionAction']);

        try {
            $this->entityManager->runInTransactionWithRetry(
                function() use ($pickingProcessId, $context, $stockReversionAction): void {
                    // We need a lock here otherwise the forthcoming select would produce phantom reads in the
                    // transaction of the cancel-method.
                    $this->entityManager->lockPessimistically(
                        PickingProcessDefinition::class,
                        ['id' => $pickingProcessId],
                        $context,
                    );

                    /** @var PickingProcessEntity $pickingProcess */
                    $pickingProcess = $this->entityManager->findByPrimaryKey(
                        PickingProcessDefinition::class,
                        $pickingProcessId,
                        $context,
                        ['state'],
                    );
                    if (!$pickingProcess) {
                        throw PickingProcessException::pickingProcessNotFound($pickingProcessId);
                    }

                    if ($pickingProcess->getState()->getTechnicalName() !== PickingProcessStateMachine::STATE_CANCELLED) {
                        $this->pickingProcessService->cancel(
                            $pickingProcessId,
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
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
        );
    }

    #[JsonValidation(schemaFilePath: 'payload-cancel-picking-processes-by-criteria.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/cancel-picking-processes-by-criteria', methods: ['PUT'])]
    public function cancelPickingProcessesByCriteria(Request $request, Context $context): Response
    {
        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $request->get('criteria'),
            PickingProcessDefinition::class,
        );

        $sanitizedCriteria = Criteria::createFrom($criteria);
        $sanitizedCriteria->setLimit(null);
        $sanitizedCriteria->setOffset(null);

        $pickingProcessIds = $this->entityManager->findIdsBy(
            PickingProcessDefinition::class,
            $sanitizedCriteria,
            $context,
        );

        if (count($pickingProcessIds) === 0) {
            return new JsonResponse();
        }

        $exceptions = new JsonApiErrors();

        foreach ($pickingProcessIds as $pickingProcessId) {
            try {
                $this->pickingProcessService->cancel(
                    $pickingProcessId,
                    $context,
                    stockReversionAction: StockReversionAction::StockToUnknownLocation,
                );
            } catch (PickingProcessException $exception) {
                $exceptions->addErrors(...$exception->serializeToJsonApiErrors()->getErrors());

                continue;
            }
        }

        if ($exceptions->count() > 0) {
            return $exceptions->toJsonApiErrorResponse();
        }

        return new JsonResponse();
    }

    #[ApiLayer(ids: [
        ApiVersion20230721ProductVariantApiLayer::class,
        ApiVersion20240725TrackingCodeApiLayer::class,
        ApiVersion20260122TrackingCodeApiLayer::class,
        ApiVersion20251111SingleItemOrdersPickingModeApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-defer-picking-process.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/defer-picking-process', methods: ['PUT'])]
    public function deferPickingProcess(Request $request, Context $context): Response
    {
        $pickingProcessId = $request->request->all()['pickingProcessId'];
        /** @var PickingProcessEntity $pickingProcess */
        $pickingProcess = $this->entityManager->findByPrimaryKey(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
            ['state'],
        );
        if (!$pickingProcess) {
            return $this->createPickingProcessNotFoundBadRequestErrorResponse($pickingProcessId);
        }
        if ($pickingProcess->getState()->getTechnicalName() !== PickingProcessStateMachine::STATE_DEFERRED) {
            try {
                $this->pickingProcessService->defer(
                    $pickingProcessId,
                    $context,
                );
            } catch (PickingProcessException $exception) {
                return $exception
                    ->serializeToJsonApiErrors()
                    ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
        );
    }

    #[JsonValidation(schemaFilePath: 'payload-defer-picking-processes-by-criteria.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/defer-picking-processes-by-criteria', methods: ['PUT'])]
    public function deferPickingProcessesByCriteria(Request $request, Context $context): Response
    {
        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $request->get('criteria'),
            PickingProcessDefinition::class,
        );

        $sanitizedCriteria = Criteria::createFrom($criteria);
        $sanitizedCriteria->setLimit(null);
        $sanitizedCriteria->setOffset(null);

        $pickingProcessIds = $this->entityManager->findIdsBy(
            PickingProcessDefinition::class,
            $sanitizedCriteria,
            $context,
        );

        if (count($pickingProcessIds) === 0) {
            return new JsonResponse();
        }

        $exceptions = new JsonApiErrors();

        foreach ($pickingProcessIds as $pickingProcessId) {
            try {
                $this->pickingProcessService->defer($pickingProcessId, $context);
            } catch (PickingProcessException $exception) {
                $exceptions->addErrors(...$exception->serializeToJsonApiErrors()->getErrors());

                continue;
            }
        }

        if ($exceptions->count() > 0) {
            return $exceptions->toJsonApiErrorResponse();
        }

        return new JsonResponse();
    }

    #[ApiLayer(ids: [
        ApiVersion20230721ProductVariantApiLayer::class,
        ApiVersion20240721PickingProfileApiLayer::class,
        ApiVersion20240725TrackingCodeApiLayer::class,
        ApiVersion20260122TrackingCodeApiLayer::class,
        ApiVersion20251111SingleItemOrdersPickingModeApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-continue-picking-process.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/continue-picking-process', methods: ['PUT'])]
    public function continuePickingProcess(Request $request, Context $context): Response
    {
        $requestPayload = $request->request->all();
        $pickingProcessId = $requestPayload['pickingProcessId'];
        $pickingProfileId = $requestPayload['pickingProfileId'];
        // Old versions of the App do not send the `warehouseId` parameter
        $warehouseId = $requestPayload['warehouseId'] ?? null;
        /** @var PickingProcessEntity $pickingProcess */
        $pickingProcess = $this->entityManager->findByPrimaryKey(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
            [
                'state',
                'device',
            ],
        );
        if (!$pickingProcess) {
            return $this->createPickingProcessNotFoundBadRequestErrorResponse($pickingProcessId);
        }
        if (!$this->doesPickingProfileExist($pickingProfileId, $context)) {
            return self::createPickingProfileNotFoundResponse();
        }
        if ($pickingProcess->getState()->getTechnicalName() !== PickingProcessStateMachine::STATE_IN_PROGRESS) {
            try {
                $this->entityManager->runInTransactionWithRetry(function() use ($pickingProfileId, $context, $pickingProcessId, $warehouseId): void {
                    if ($warehouseId !== null) {
                        $this->pickingProcessService->moveIntoWarehouse($pickingProcessId, $warehouseId, $context);
                    }
                    $this->pickingProcessService->startOrContinue(
                        pickingProcessId: $pickingProcessId,
                        pickingProfileId: $pickingProfileId,
                        context: $context,
                    );
                });
            } catch (PickingProcessException $exception) {
                return $exception
                    ->serializeToJsonApiErrors()
                    ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
            }
        } elseif ($pickingProcess->getDeviceId() && $pickingProcess->getDeviceId() !== Device::getFromContext($context)->getId()) {
            return PickingProcessException::invalidDevice(
                $pickingProcess->getDeviceId(),
                $pickingProcess->getDevice()?->getName(),
                $pickingProcess->getId(),
            )
                ->serializeToJsonApiErrors()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
        );
    }

    #[ApiLayer(ids: [
        ApiVersion20230721ProductVariantApiLayer::class,
        ApiVersion20240725TrackingCodeApiLayer::class,
        ApiVersion20260122TrackingCodeApiLayer::class,
        ApiVersion20251111SingleItemOrdersPickingModeApiLayer::class,
    ])]
    #[JsonValidation(schemaFilePath: 'payload-split-picking-process.schema.json')]
    #[Route(path: '/api/_action/pickware-wms/split-picking-process', methods: ['PUT'])]
    public function splitPickingProcess(Request $request, Context $context): Response
    {
        $requestPayload = $request->request->all();
        $pickingProcessId = $requestPayload['pickingProcessId'];
        /** @var PickingProcessEntity $pickingProcess */
        $pickingProcess = $this->entityManager->findByPrimaryKey(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
        );
        if (!$pickingProcess) {
            return $this->createPickingProcessNotFoundBadRequestErrorResponse($pickingProcessId);
        }

        $newPickingProcessPayload = $requestPayload['newPickingProcess'];
        $newPickingProcess = $this->entityManager->findByPrimaryKey(
            PickingProcessDefinition::class,
            $newPickingProcessPayload['id'],
            $context,
        );
        if (!$newPickingProcess) {
            $newPickingProcessPayload['warehouseId'] = $pickingProcess->getWarehouseId();
            $newPickingProcessPayload['deliveries'] = [];

            // We don't need a picking process creation lock here because we create the picking process without any
            // deliveries that could be picked up by another picking process
            $this->entityManager->runInTransactionWithRetry(
                function() use ($context, $requestPayload, $newPickingProcessPayload, $pickingProcessId): void {
                    $this->pickingProcessCreation->createPickingProcess(
                        pickingProcessPayload: $newPickingProcessPayload,
                        pickingProfileId: null,
                        context: $context,
                    );
                    $this->pickingProcessService->moveDeliveries(
                        $pickingProcessId,
                        $newPickingProcessPayload['id'],
                        $requestPayload['deliveryIds'],
                        $context,
                    );
                },
            );
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
        );
    }

    #[Route(path: '/api/_action/pickware-wms/picking-process-receipt', methods: ['GET'])]
    public function getPickingProcessReceipt(
        #[MapQueryParameter]
        string $pickingProcessId,
        #[MapQueryParameter]
        ?string $languageId,
        Context $context,
    ): Response {
        /** @var PickingProcessEntity $pickingProcess */
        $pickingProcess = $this->entityManager->findByPrimaryKey(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
        );
        if (!$pickingProcess) {
            return $this->createPickingProcessNotFoundBadRequestErrorResponse($pickingProcessId);
        }

        $languageId ??= Defaults::LANGUAGE_SYSTEM;

        try {
            $templateVariables = $this->pickingProcessReceiptContentGenerator->generateForPickingProcess(
                $pickingProcessId,
                $languageId,
                $context,
            );
            $renderedDocument = $this->pickingProcessReceiptDocumentGenerator->generate($templateVariables, $languageId, $context);
        } catch (PickingProcessException $exception) {
            return $exception
                ->serializeToJsonApiErrors()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return DocumentBundleResponseFactory::createPdfResponse(
            $renderedDocument->getContent(),
            $renderedDocument->getName(),
            $renderedDocument->getContentType(),
        );
    }

    private function createPickingProcessNotFoundBadRequestErrorResponse(string $pickingProcessId): Response
    {
        return PickingProcessException::pickingProcessNotFound($pickingProcessId)
            ->serializeToJsonApiErrors()
            ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
    }

    private function createDeliveryNotFoundResponse(string $deliveryId): Response
    {
        return PickingProcessException::deliveryNotFound($deliveryId)
            ->serializeToJsonApiErrors()
            ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
    }

    private function doesPickingProfileExist(string $pickingProfileId, Context $context): bool
    {
        if ($pickingProfileId === ApiVersion20240721PickingProfileApiLayer::PICKING_PROFILE_ID) {
            return true;
        }

        return $this->entityManager->findByPrimaryKey(
            PickingProfileDefinition::class,
            $pickingProfileId,
            $context,
        ) !== null;
    }

    private static function createPickingProfileNotFoundResponse(): Response
    {
        return PickingProcessException::pickingProfileNotFound()
            ->serializeToJsonApiErrors()
            ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
    }
}
