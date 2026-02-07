<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess;

use Pickware\DalBundle\EntityCollectionExtension;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerDefinition;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareWms\Delivery\DeliveryLineItemCalculator;
use Pickware\PickwareWms\Delivery\DeliveryService;
use Pickware\PickwareWms\Delivery\DeliveryStateTransitionService;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Pickware\PickwareWms\Device\Device;
use Pickware\PickwareWms\Logging\PickingProcessLoggingService;
use Pickware\PickwareWms\OrderStateValidation\OrderStateValidation;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessEntity;
use Pickware\PickwareWms\PickingProperty\PickingPropertyService;
use Pickware\PickwareWms\Statistic\Model\PickingProcessLifecycleEventType;
use Pickware\PickwareWms\Statistic\Service\PickingProcessLifecycleEventService;
use Pickware\PickwareWms\Statistic\Service\PickStatisticService;
use Pickware\PickwareWms\StockContainerClearing\StockContainerClearingService;
use Pickware\PickwareWms\StockingProcess\StockingProcessService;
use Pickware\PickwareWms\StockReservation\StockReservationService;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\EntityStateDefinition;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\StateTransitionBatchService;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;

class PickingProcessService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly PickingProcessStateTransitionService $pickingProcessStateTransitionService,
        private readonly StateTransitionBatchService $stateTransitionBatchService,
        private readonly StockReservationService $stockReservationService,
        private readonly OrderStateValidation $orderStateValidation,
        private readonly StockContainerClearingService $restockingService,
        private readonly PickingProcessCleanupService $pickingProcessCleanupService,
        private readonly DeliveryLineItemCalculator $deliveryLineItemCalculator,
        private readonly PickingPropertyService $pickingPropertyService,
        private readonly PickingProcessLoggingService $pickingProcessLogService,
        private readonly StockingProcessService $stockingProcessService,
        private readonly PickStatisticService $pickingStatisticService,
        private readonly PickingProcessLifecycleEventService $pickingProcessLifecycleEventService,
        private readonly DeliveryService $deliveryService,
        private readonly DeliveryStateTransitionService $deliveryStateTransitionService,
    ) {}

    public function startOrContinue(string $pickingProcessId, string $pickingProfileId, Context $context): void
    {
        // Run this method in a transaction so that the logic is executed in full or not at all in case of an error.
        $this->entityManager->runInTransactionWithRetry(
            function() use ($pickingProcessId, $pickingProfileId, $context): void {
                // The state transition validates the current picking process state implicitly. When we lock the picking
                // process and execute the state transition first, we ensure that no two ::start() functions are
                // executed on the same picking process in parallel. (Any other execution that waits for the lock
                // release will fail when trying to execute the state transition)
                $this->entityManager->lockPessimistically(
                    PickingProcessDefinition::class,
                    ['id' => $pickingProcessId],
                    $context,
                );

                /** @var PickingProcessEntity $pickingProcess */
                $pickingProcess = $this->entityManager->getByPrimaryKey(
                    PickingProcessDefinition::class,
                    $pickingProcessId,
                    $context,
                    [
                        'deliveries.state',
                        'state',
                    ],
                );

                $this->pickingProcessStateTransitionService->tryPickingProcessStateTransition(
                    $pickingProcessId,
                    PickingProcessStateMachine::TRANSITION_CONTINUE,
                    $context,
                    $pickingProfileId,
                );

                $stateErrors = $this->orderStateValidation->getStateViolationsForOrdersOfPickingProcess(
                    $pickingProcessId,
                    $pickingProfileId,
                    $context,
                );
                if ($stateErrors->count() > 0) {
                    throw new PickingProcessException($stateErrors);
                }

                $context->scope(
                    Context::SYSTEM_SCOPE,
                    function(Context $context) use ($pickingProcess): void {
                        $this->stateTransitionBatchService->ensureTargetStateForEntities(
                            EntityStateDefinition::order(),
                            EntityCollectionExtension::getField($pickingProcess->getDeliveries(), 'orderId'),
                            OrderStates::STATE_IN_PROGRESS,
                            $context,
                        );
                    },
                );

                $this->deliveryLineItemCalculator->recalculateDeliveryLineItems(
                    deliveryIds: EntityCollectionExtension::getField($pickingProcess->getDeliveries(), 'id'),
                    context: $context,
                );
                $this->stockReservationService->reserveStockForPickingProcess(
                    $pickingProcessId,
                    $pickingProfileId,
                    $context,
                );

                $this->entityManager->update(
                    PickingProcessDefinition::class,
                    [
                        [
                            'id' => $pickingProcessId,
                            'userId' => ContextExtension::getUserId($context),
                            'device' => Device::getFromContext($context)->toPayload(),
                        ],
                    ],
                    $context,
                );
                $this->pickingProcessLogService->logPickingProcessStartedOrContinued(
                    pickingProcessId: $pickingProcessId,
                    context: $context,
                );
            },
        );
    }

    public function pickItemIntoDelivery(
        string $deliveryId,
        PickingItem $item,
        Context $context,
    ): void {
        /** @var DeliveryEntity $delivery */
        $delivery = $this->entityManager->getByPrimaryKey(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
            [
                'state',
                'pickingProcess.state',
                'pickingProcess.device',
                'pickingProcess.shippingProcess.state',
                'pickingProcess.shippingProcess.device',
            ],
        );

        if ($delivery->getState()->getTechnicalName() !== DeliveryStateMachine::STATE_IN_PROGRESS) {
            throw PickingProcessException::invalidDeliveryStateForAction(
                $delivery->getId(),
                $delivery->getState()->getTechnicalName(),
                [DeliveryStateMachine::STATE_IN_PROGRESS],
            );
        }
        $pickingProcess = $delivery->getPickingProcess();
        $expectedPickingProcessStates = [
            PickingProcessStateMachine::STATE_IN_PROGRESS,
            PickingProcessStateMachine::STATE_PICKED,
        ];
        if (!in_array($pickingProcess->getState()->getTechnicalName(), $expectedPickingProcessStates, true)) {
            throw PickingProcessException::invalidPickingProcessStateForAction(
                $pickingProcess->getId(),
                $pickingProcess->getState()->getTechnicalName(),
                $expectedPickingProcessStates,
            );
        }

        // Validate the device to avoid parallel picking by different warehouse staff.
        $this->ensurePickingProcessOrItsShippingProcessIsAssignedToCurrentDevice($pickingProcess, $context);

        if (!$delivery->getStockContainerId()) {
            throw PickingProcessException::noStockContainerAssignedForDelivery($deliveryId);
        }

        $this->entityManager->runInTransactionWithRetry(
            function() use ($delivery, $pickingProcess, $item, $context): void {
                $this->pickingPropertyService->savePickingPropertiesForDeliveryItem(
                    $delivery->getId(),
                    $item->getPickingPropertyRecords(),
                    $item->getProductId(),
                    $context,
                );

                $this->stockReservationService->moveReservedOrFreeStock(
                    $delivery->getPickingProcess()->getId(),
                    $item->createStockMovementForDestination(
                        StockLocationReference::stockContainer($delivery->getStockContainerId()),
                        $context,
                    ),
                    $context,
                );

                // If the order was picked into the pre-collecting stock container, we do not write a statistic
                // entry at this point. The statistic entry is written when the item is picked into the picking
                // process. see PickingProcessService::pickItemIntoPickingProcess()
                $source = $item->getSource();
                if (!$pickingProcess->getPreCollectingStockContainerId()) {
                    $context->scope(Context::SYSTEM_SCOPE, fn(Context $systemScopeContext) => $this->pickingStatisticService->logPickEvent(
                        productId: $item->getProductId(),
                        binLocationId: match ($source->getLocationTypeTechnicalName()) {
                            LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION => $source->getBinLocationId(),
                            default => null,
                        },
                        pickingProcessId: $pickingProcess->getId(),
                        quantity: $item->getQuantity(),
                        context: $systemScopeContext,
                    ));
                }

                $this->pickingProcessLogService->logItemsPicked(
                    pickingProcessId: $delivery->getPickingProcess()->getId(),
                    numberOfItemsPicked: $item->getQuantity(),
                    context: $context,
                );
            },
        );
    }

    public function pickItemIntoPickingProcess(
        string $pickingProcessId,
        PickingItem $item,
        Context $context,
    ): void {
        /** @var PickingProcessEntity $pickingProcess */
        $pickingProcess = $this->entityManager->getByPrimaryKey(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
            [
                'state',
                'device',
            ],
        );

        if ($pickingProcess->getState()->getTechnicalName() !== PickingProcessStateMachine::STATE_IN_PROGRESS) {
            throw PickingProcessException::invalidPickingProcessStateForAction(
                $pickingProcess->getId(),
                $pickingProcess->getState()->getTechnicalName(),
                [PickingProcessStateMachine::STATE_IN_PROGRESS],
            );
        }

        // Validate the device to avoid parallel picking by different warehouse staff.
        $this->ensurePickingProcessIsAssignedToCurrentDevice($pickingProcess, $context);

        if (!$pickingProcess->getPreCollectingStockContainerId()) {
            throw PickingProcessException::noStockContainerAssignedForPickingProcess($pickingProcessId);
        }

        $this->stockReservationService->moveReservedOrFreeStock(
            $pickingProcessId,
            $item->createStockMovementForDestination(
                StockLocationReference::stockContainer($pickingProcess->getPreCollectingStockContainerId()),
                $context,
            ),
            $context,
        );

        $source = $item->getSource();
        $context->scope(Context::SYSTEM_SCOPE, fn(Context $systemScopeContext) => $this->pickingStatisticService->logPickEvent(
            productId: $item->getProductId(),
            binLocationId: match ($source->getLocationTypeTechnicalName()) {
                LocationTypeDefinition::TECHNICAL_NAME_BIN_LOCATION => $source->getBinLocationId(),
                default => null,
            },
            pickingProcessId: $pickingProcess->getId(),
            quantity: $item->getQuantity(),
            context: $systemScopeContext,
        ));
        $this->pickingProcessLogService->logItemsPicked(
            pickingProcessId: $pickingProcessId,
            numberOfItemsPicked: $item->getQuantity(),
            context: $context,
        );
    }

    public function moveIntoWarehouse(string $pickingProcessId, string $warehouseId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(
            function() use ($pickingProcessId, $warehouseId, $context): void {
                /** @var PickingProcessEntity $pickingProcess */
                $pickingProcess = $this->entityManager->getByPrimaryKey(
                    PickingProcessDefinition::class,
                    $pickingProcessId,
                    $context,
                    ['deliveries'],
                );

                $stockContainerIds = array_values(array_filter([
                    $pickingProcess->getPreCollectingStockContainerId(),
                    ...$pickingProcess->getDeliveries()->map(
                        fn(DeliveryEntity $delivery) => $delivery->getStockContainerId(),
                    ),
                ]));

                $this->entityManager->update(
                    StockContainerDefinition::class,
                    array_map(
                        fn(string $stockContainerId) => [
                            'id' => $stockContainerId,
                            'warehouseId' => $warehouseId,
                        ],
                        $stockContainerIds,
                    ),
                    $context,
                );
                $this->entityManager->update(
                    PickingProcessDefinition::class,
                    [
                        [
                            'id' => $pickingProcessId,
                            'warehouseId' => $warehouseId,
                        ],
                    ],
                    $context,
                );
            },
        );
    }

    public function takeOver(string $pickingProcessId, ?string $pickingProfileId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($pickingProcessId, $pickingProfileId, $context): void {
            $this->entityManager->lockPessimistically(
                PickingProcessDefinition::class,
                ['id' => $pickingProcessId],
                $context,
            );

            /** @var PickingProcessEntity $pickingProcess */
            $pickingProcess = $this->entityManager->getByPrimaryKey(
                PickingProcessDefinition::class,
                $pickingProcessId,
                $context,
                ['state'],
            );

            $currentPickingProcessState = $pickingProcess->getState()->getTechnicalName();
            if ($currentPickingProcessState !== PickingProcessStateMachine::STATE_IN_PROGRESS) {
                throw PickingProcessException::invalidPickingProcessStateForAction(
                    $pickingProcessId,
                    $currentPickingProcessState,
                    [PickingProcessStateMachine::STATE_IN_PROGRESS],
                );
            }

            $this->entityManager->update(
                PickingProcessDefinition::class,
                [
                    [
                        'id' => $pickingProcessId,
                        'userId' => ContextExtension::getUserId($context),
                        'device' => Device::getFromContext($context)->toPayload(),
                    ],
                ],
                $context,
            );
            $this->pickingProcessLifecycleEventService->writePickingProcessLifecycleEvent(
                PickingProcessLifecycleEventType::TakeOver,
                $pickingProcessId,
                $pickingProfileId,
                $context,
            );
        });
    }

    public function defer(string $pickingProcessId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(
            function() use ($pickingProcessId, $context): void {
                $this->entityManager->lockPessimistically(
                    PickingProcessDefinition::class,
                    ['id' => $pickingProcessId],
                    $context,
                );

                /** @var PickingProcessEntity $pickingProcess */
                $pickingProcess = $this->entityManager->getByPrimaryKey(
                    PickingProcessDefinition::class,
                    $pickingProcessId,
                    $context,
                    ['state'],
                );

                $this->pickingProcessStateTransitionService->tryPickingProcessStateTransition(
                    $pickingProcessId,
                    PickingProcessStateMachine::TRANSITION_DEFER,
                    $context,
                );

                // Remove remaining reserved items from the picking process, they will be recreated when continuing the
                // picking process.
                $this->stockReservationService->clearStockReservationsOfPickingProcess($pickingProcessId, $context);

                $this->pickingProcessLogService->logPickingProcessDeferred(
                    pickingProcessId: $pickingProcessId,
                    context: $context,
                );

                // Clear user and device only after logging the picking process deferred event. Otherwise, the picking
                // process log would be incomplete.
                $this->entityManager->update(
                    PickingProcessDefinition::class,
                    [
                        [
                            'id' => $pickingProcessId,
                            'userId' => null,
                            'deviceId' => null,
                        ],
                    ],
                    $context,
                );
            },
        );
    }

    public function complete(string $pickingProcessId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(
            function() use ($pickingProcessId, $context): void {
                // The state transition validates the current picking process state implicitly. When we lock the picking
                // process and execute the state transition first, we ensure that no two ::complete() functions are
                // executed on the same picking process in parallel. (Any other execution that waits for the lock
                // release will fail when trying to execute the state transition)
                $this->entityManager->lockPessimistically(PickingProcessDefinition::class, ['id' => $pickingProcessId], $context);

                /** @var PickingProcessEntity $pickingProcess */
                $pickingProcess = $this->entityManager->getByPrimaryKey(
                    PickingProcessDefinition::class,
                    $pickingProcessId,
                    $context,
                    [
                        'state',
                        'device',
                        'deliveries.state',
                        'deliveries.stockContainer.stocks',
                        'preCollectingStockContainer.stocks',
                    ],
                );

                $this->pickingProcessStateTransitionService->tryPickingProcessStateTransition(
                    $pickingProcessId,
                    PickingProcessStateMachine::TRANSITION_COMPLETE,
                    $context,
                );

                // Validate the device to avoid parallel picking by different warehouse staff.
                $this->ensurePickingProcessIsAssignedToCurrentDevice($pickingProcess, $context);

                if (
                    $pickingProcess->getPreCollectingStockContainer()
                    && $pickingProcess->getPreCollectingStockContainer()->getStocks()->count() > 0
                ) {
                    $this->restockingService->putStockInStockContainersToUnknownLocationInWarehouse(
                        [$pickingProcess->getPreCollectingStockContainer()->getId()],
                        $pickingProcess->getWarehouseId(),
                        $context,
                    );
                }

                foreach ($pickingProcess->getDeliveries() as $delivery) {
                    $stockContainer = $delivery->getStockContainer();
                    if (!$stockContainer || $stockContainer->getStocks()->count() === 0) {
                        // If nothing was picked into the delivery, it is cancelled, so it does not appear in the app
                        // anymore.
                        $this->deliveryStateTransitionService->tryDeliveryStateTransition(
                            $delivery->getId(),
                            DeliveryStateMachine::TRANSITION_CANCEL,
                            $context,
                        );
                    } else {
                        $this->deliveryStateTransitionService->tryDeliveryStateTransition(
                            $delivery->getId(),
                            DeliveryStateMachine::TRANSITION_COMPLETE,
                            $context,
                        );
                    }
                }

                // There are several cases where stock reservations can be left over:
                // - Stock was picked from other locations
                // - Deliveries were cancelled during the picking
                // - Partial deliveries were performed
                $this->stockReservationService->clearStockReservationsOfPickingProcess($pickingProcessId, $context);
                $this->pickingProcessCleanupService->removeNumbersFromUnusedStockContainersOfPickingProcess(
                    $pickingProcessId,
                    $context,
                );

                $this->pickingProcessLogService->logPickingProcessCompleted(
                    pickingProcessId: $pickingProcessId,
                    context: $context,
                );

                // Clear user and device only after logging the picking process completed event. Otherwise, the picking
                // process log would be incomplete.
                $this->entityManager->update(
                    PickingProcessDefinition::class,
                    [
                        [
                            'id' => $pickingProcessId,
                            'userId' => null,
                            'deviceId' => null,
                        ],
                    ],
                    $context,
                );
            },
        );
    }

    public function completePreCollecting(string $pickingProcessId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(
            function() use ($pickingProcessId, $context): void {
                // The state transition validates the current picking process state implicitly. When we lock the picking
                // process and execute the state transition first, we ensure that no two ::complete() functions are
                // executed on the same picking process in parallel. (Any other execution that waits for the lock
                // release will fail when trying to execute the state transition)
                $this->entityManager->lockPessimistically(PickingProcessDefinition::class, ['id' => $pickingProcessId], $context);

                /** @var PickingProcessEntity $pickingProcess */
                $pickingProcess = $this->entityManager->getByPrimaryKey(
                    PickingProcessDefinition::class,
                    $pickingProcessId,
                    $context,
                    [
                        'state',
                        'device',
                    ],
                );

                $this->pickingProcessStateTransitionService->tryPickingProcessStateTransition(
                    $pickingProcessId,
                    PickingProcessStateMachine::TRANSITION_COMPLETE,
                    $context,
                );

                // Validate the device to avoid parallel picking by different warehouse staff.
                $this->ensurePickingProcessIsAssignedToCurrentDevice($pickingProcess, $context);

                // There are several cases where stock reservations can be left over:
                // - Stock was picked from other locations
                $this->stockReservationService->clearStockReservationsOfPickingProcess($pickingProcessId, $context);
                $this->pickingProcessCleanupService->cancelDeliveriesThatAreNotFulfillableWithPreCollectedStock($pickingProcessId, $context);

                $this->pickingProcessLogService->logPickingProcessPreCollectingCompleted(
                    pickingProcessId: $pickingProcessId,
                    context: $context,
                );

                // Clear user and device only after logging the picking process completed event. Otherwise, the picking
                // process log would be incomplete.
                $this->entityManager->update(
                    PickingProcessDefinition::class,
                    [
                        [
                            'id' => $pickingProcessId,
                            'userId' => null,
                            'deviceId' => null,
                        ],
                    ],
                    $context,
                );
            },
        );
    }

    /**
     * @return array<string> The IDs of all delivery and pre-collecting stock containers that still contain stock.
     */
    public function cancelAndCheckStockContainersForStock(string $pickingProcessId, Context $context): array
    {
        return $this->entityManager->runInTransactionWithRetry(
            function() use ($pickingProcessId, $context): array {
                // The state transition validates the current picking process state implicitly. When we lock the picking
                // process and execute the state transition first, we ensure that no two
                // ::cancelAndCheckStockContainersForStock() functions are executed on the same picking process in
                // parallel. (Any other execution that waits for the lock release will fail when trying to execute the
                // state transition).
                $this->entityManager->lockPessimistically(
                    PickingProcessDefinition::class,
                    ['id' => $pickingProcessId],
                    $context,
                );

                /** @var PickingProcessEntity $pickingProcess */
                $pickingProcess = $this->entityManager->getByPrimaryKey(
                    PickingProcessDefinition::class,
                    $pickingProcessId,
                    $context,
                    [
                        'state',
                        'deliveries.state',
                        'preCollectingStockContainer.stocks',
                    ],
                );

                $this->pickingProcessStateTransitionService->tryPickingProcessStateTransition(
                    $pickingProcessId,
                    PickingProcessStateMachine::TRANSITION_CANCEL,
                    $context,
                );
                $this->pickingProcessLogService->logPickingProcessCancelled(
                    pickingProcessId: $pickingProcessId,
                    context: $context,
                );
                $deliveriesToBeStocked = $pickingProcess
                    ->getDeliveries()
                    ->filter(function(DeliveryEntity $delivery) use ($context): bool {
                        if ($delivery->getState()->getTechnicalName() === DeliveryStateMachine::STATE_IN_PROGRESS) {
                            return $this->deliveryService->cancelAndCheckStockContainerForStock(
                                $delivery->getId(),
                                $context,
                            );
                        }

                        return false;
                    });

                $nonEmptyStockContainerIds = array_values(array_filter(EntityCollectionExtension::getField(
                    $deliveriesToBeStocked,
                    'stockContainerId',
                )));
                if (
                    $pickingProcess->getPreCollectingStockContainer()
                    && $pickingProcess->getPreCollectingStockContainer()->getStocks()->count() > 0
                ) {
                    $nonEmptyStockContainerIds[] = $pickingProcess->getPreCollectingStockContainerId();
                }

                $this->pickingProcessCleanupService->removeNumbersFromUnusedStockContainersOfPickingProcess(
                    $pickingProcessId,
                    $context,
                );

                return $nonEmptyStockContainerIds;
            },
        );
    }

    public function cancel(string $pickingProcessId, Context $context, StockReversionAction $stockReversionAction): void
    {
        $this->entityManager->runInTransactionWithRetry(
            function() use ($pickingProcessId, $context, $stockReversionAction): void {
                // The state transition validates the current picking process state implicitly. When we lock the picking
                // process and execute the state transition first, we ensure that no two ::cancel() functions are
                // executed on the same picking process in parallel. (Any other execution that waits for the lock
                // release will fail when trying to execute the state transition).
                $this->entityManager->lockPessimistically(
                    PickingProcessDefinition::class,
                    ['id' => $pickingProcessId],
                    $context,
                );

                /** @var PickingProcessEntity $pickingProcess */
                $pickingProcess = $this->entityManager->getByPrimaryKey(
                    PickingProcessDefinition::class,
                    $pickingProcessId,
                    $context,
                );

                $nonEmptyStockContainerIds = $this->cancelAndCheckStockContainersForStock(
                    $pickingProcessId,
                    $context,
                );

                if (!empty($nonEmptyStockContainerIds)) {
                    switch ($stockReversionAction) {
                        case StockReversionAction::CreateStockingProcess:
                            $this->stockingProcessService->createDeferredStockingProcess(
                                $pickingProcess->getWarehouseId(),
                                array_map(
                                    fn($stockContainerId) => [
                                        'stockContainer' => [
                                            'id' => $stockContainerId,
                                        ],
                                    ],
                                    $nonEmptyStockContainerIds,
                                ),
                                $context,
                            );
                            break;
                        case StockReversionAction::StockToUnknownLocation:
                            $this->restockingService->putStockInStockContainersToUnknownLocationInWarehouse(
                                $nonEmptyStockContainerIds,
                                $pickingProcess->getWarehouseId(),
                                $context,
                            );
                            break;
                    }
                }

                $this->stockReservationService->clearStockReservationsOfPickingProcess($pickingProcessId, $context);

                // Clear user and device only after logging the picking process cancelled event. Otherwise, the picking
                // process log would be incomplete.
                $this->entityManager->update(
                    PickingProcessDefinition::class,
                    [
                        [
                            'id' => $pickingProcessId,
                            'userId' => null,
                            'deviceId' => null,
                        ],
                    ],
                    $context,
                );
            },
        );
    }

    public function assignStockContainerToDelivery(
        string $deliveryId,
        string $stockContainerId,
        Context $context,
    ): void {
        /** @var DeliveryEntity $delivery */
        $delivery = $this->entityManager->getByPrimaryKey(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
            [
                'stockContainer',
                'pickingProcess.device',
                'pickingProcess.state',
                'pickingProcess.shippingProcess.device',
                'pickingProcess.shippingProcess.state',
            ],
        );

        $pickingProcess = $delivery->getPickingProcess();
        $expectedPickingProcessStates = [
            PickingProcessStateMachine::STATE_IN_PROGRESS,
            PickingProcessStateMachine::STATE_PICKED,
        ];
        if (!in_array($pickingProcess->getState()->getTechnicalName(), $expectedPickingProcessStates, true)) {
            throw PickingProcessException::invalidPickingProcessStateForAction(
                $pickingProcess->getId(),
                $pickingProcess->getState()->getTechnicalName(),
                $expectedPickingProcessStates,
            );
        }

        $this->ensurePickingProcessOrItsShippingProcessIsAssignedToCurrentDevice($pickingProcess, $context);

        if ($delivery->getStockContainerId() !== null) {
            throw PickingProcessException::deliveryAlreadyHasStockContainer(
                $deliveryId,
                $delivery->getStockContainerId(),
                $delivery->getStockContainer()->getNumber(),
            );
        }

        $this->entityManager->update(
            DeliveryDefinition::class,
            [
                [
                    'id' => $deliveryId,
                    'stockContainerId' => $stockContainerId,
                ],
            ],
            $context,
        );
    }

    private function ensurePickingProcessIsAssignedToCurrentDevice(
        PickingProcessEntity $pickingProcess,
        Context $context,
    ): void {
        if ($pickingProcess->getDeviceId() === null) {
            return;
        }

        if ($pickingProcess->getDeviceId() !== Device::getFromContext($context)->getId()) {
            throw PickingProcessException::invalidDevice(
                $pickingProcess->getDeviceId(),
                $pickingProcess->getDevice()?->getName(),
                $pickingProcess->getId(),
            );
        }
    }

    private function ensurePickingProcessOrItsShippingProcessIsAssignedToCurrentDevice(
        PickingProcessEntity $pickingProcess,
        Context $context,
    ): void {
        $deviceIdToCheck = $pickingProcess->getDeviceId() ?? $pickingProcess->getShippingProcess()?->getDeviceId();
        if ($deviceIdToCheck === null) {
            return;
        }

        if ($deviceIdToCheck !== Device::getFromContext($context)->getId()) {
            throw PickingProcessException::invalidDevice(
                $deviceIdToCheck,
                $pickingProcess->getDevice()?->getName() ?? $pickingProcess->getShippingProcess()?->getDevice()?->getName(),
                $pickingProcess->getId(),
            );
        }
    }

    /**
     * @param string[] $deliveryIds
     */
    public function moveDeliveries(
        string $sourcePickingProcessId,
        string $destinationPickingProcessId,
        array $deliveryIds,
        Context $context,
    ): void {
        /** @var PickingProcessEntity $sourcePickingProcess */
        $sourcePickingProcess = $this->entityManager->getByPrimaryKey(
            PickingProcessDefinition::class,
            $sourcePickingProcessId,
            $context,
            [
                'deliveries',
                'state',
                'device',
            ],
        );
        /** @var PickingProcessEntity $destinationPickingProcess */
        $destinationPickingProcess = $this->entityManager->getByPrimaryKey(
            PickingProcessDefinition::class,
            $destinationPickingProcessId,
            $context,
            ['state'],
        );

        $deliveryIds = array_values(array_unique($deliveryIds));

        // The following code checks with array_diff whether the passed delivery IDs are deliveries of the source
        // picking process at all.
        $foreignDeliveryIds = array_diff(
            $deliveryIds,
            EntityCollectionExtension::getField($sourcePickingProcess->getDeliveries(), 'id'),
        );
        if (count($foreignDeliveryIds) !== 0) {
            throw PickingProcessException::deliveriesDoNotBelongToPickingProcess(
                $foreignDeliveryIds,
                $sourcePickingProcessId,
            );
        }

        if ($sourcePickingProcess->getDeliveries()->count() === count($deliveryIds)) {
            throw PickingProcessException::pickingProcessCannotBeEmpty();
        }

        $allowedSourceStates = [
            PickingProcessStateMachine::STATE_IN_PROGRESS,
            PickingProcessStateMachine::STATE_DEFERRED,
        ];
        if (!in_array($sourcePickingProcess->getState()->getTechnicalName(), $allowedSourceStates, true)) {
            throw PickingProcessException::invalidPickingProcessStateForAction(
                $sourcePickingProcessId,
                $sourcePickingProcess->getState()->getTechnicalName(),
                $allowedSourceStates,
            );
        }

        if ($sourcePickingProcess->getState()->getTechnicalName() === PickingProcessStateMachine::STATE_IN_PROGRESS) {
            $this->ensurePickingProcessIsAssignedToCurrentDevice($sourcePickingProcess, $context);
        }

        if ($destinationPickingProcess->getState()->getTechnicalName() !== PickingProcessStateMachine::STATE_DEFERRED) {
            throw PickingProcessException::invalidPickingProcessStateForAction(
                $destinationPickingProcessId,
                $destinationPickingProcess->getState()->getTechnicalName(),
                [PickingProcessStateMachine::STATE_DEFERRED],
            );
        }

        $updatePayload = array_map(
            fn(string $deliveryId) => [
                'id' => $deliveryId,
                'pickingProcessId' => $destinationPickingProcessId,
            ],
            $deliveryIds,
        );
        $this->entityManager->update(DeliveryDefinition::class, $updatePayload, $context);
    }
}
