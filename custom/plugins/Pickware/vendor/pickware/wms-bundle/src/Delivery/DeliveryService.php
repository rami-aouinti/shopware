<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Delivery;

use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\InstallationLibrary\StateMachine\StateMachineState;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\OrderParcelException;
use Pickware\PickwareErpStarter\OrderShipping\OrderParcelService;
use Pickware\PickwareErpStarter\OrderShipping\TrackingCode;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyRecord;
use Pickware\PickwareErpStarter\PickingProperty\PickingPropertyRecordValue;
use Pickware\PickwareWms\Config\FeatureFlags\CreateEnclosedReturnLabelFeatureFlag;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Pickware\PickwareWms\Delivery\Model\DeliveryParcelDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryParcelEntity;
use Pickware\PickwareWms\DocumentPrintingConfig\Model\DocumentPrintingConfigCollection;
use Pickware\PickwareWms\DocumentPrintingConfig\Model\DocumentPrintingConfigEntity;
use Pickware\PickwareWms\PickingProcess\DeliveryStateMachine;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProcess\PickingProcessCleanupService;
use Pickware\PickwareWms\PickingProcess\PickingProcessException;
use Pickware\PickwareWms\PickingProcess\StockReversionAction;
use Pickware\PickwareWms\PickingProperty\Model\PickingPropertyDeliveryRecordEntity;
use Pickware\PickwareWms\PickingProperty\Model\PickingPropertyDeliveryRecordValueEntity;
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventType;
use Pickware\PickwareWms\Statistic\Service\DeliveryLifecycleEventService;
use Pickware\PickwareWms\StockContainerClearing\StockContainerClearingService;
use Pickware\PickwareWms\StockingProcess\StockingProcessService;
use Pickware\ShippingBundle\Shipment\Model\TrackingCodeEntity;
use Pickware\ShippingBundle\Shipment\ShipmentBlueprint;
use Pickware\ShopwareExtensionsBundle\OrderDelivery\OrderDeliveryCollectionExtension;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Transition;

class DeliveryService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly DeliveryStateTransitionService $deliveryStateTransitionService,
        private readonly DeliveryDocumentService $deliveryDocumentService,
        private readonly DeliveryShipmentCreation $deliveryShipmentCreation,
        private readonly OrderParcelService $orderParcelService,
        private readonly StockContainerClearingService $stockContainerClearingService,
        private readonly PickingProcessCleanupService $pickingProcessCleanupService,
        private readonly FeatureFlagService $featureFlagService,
        private readonly StockingProcessService $stockingProcessService,
        private readonly DeliveryLifecycleEventService $deliveryLifecycleEventService,
    ) {}

    public function createOrderDocuments(string $deliveryId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(
            function() use ($deliveryId, $context): void {
                // The state transition validates the current picking process state implicitly. When we lock the picking
                // process and execute the state transition first, we ensure that no two ::createOrderDocuments()
                // functions are executed on the same picking process in parallel. (Any other execution that waits for
                // the lock release will fail when trying to execute the state transition)
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
                    [
                        'state',
                        'order.deliveries.shippingMethod.extensions.pickwareWmsDocumentPrintingConfigs',
                        'order.deliveries.shippingMethod.extensions.pickwareWmsShippingMethodConfigs',
                    ],
                );

                $this->deliveryStateTransitionService->tryDeliveryStateTransition(
                    $deliveryId,
                    DeliveryStateMachine::TRANSITION_CREATE_DOCUMENTS,
                    $context,
                );

                $primaryOrderDelivery = OrderDeliveryCollectionExtension::primaryOrderDelivery(
                    $delivery->getOrder()->getDeliveries(),
                );
                if (!$primaryOrderDelivery) {
                    throw PickingProcessException::noOrderDeliveries($delivery->getOrderId());
                }

                /** @var DocumentTypeEntity $invoiceDocumentType */
                $invoiceDocumentType = $this->entityManager->getOneBy(
                    DocumentTypeDefinition::class,
                    ['technicalName' => InvoiceRenderer::TYPE],
                    $context,
                );

                /** @var DocumentTypeEntity $deliveryNoteDocumentType */
                $deliveryNoteDocumentType = $this->entityManager->getOneBy(
                    DocumentTypeDefinition::class,
                    ['technicalName' => DeliveryNoteRenderer::TYPE],
                    $context,
                );

                // Find matching printing configuration for documents that will be printed in a later process.
                /** @var DocumentPrintingConfigCollection $documentPrintingConfigurations */
                $documentPrintingConfigurations = $primaryOrderDelivery->getShippingMethod()->getExtension(
                    'pickwareWmsDocumentPrintingConfigs',
                );

                $invoicePrintingConfig = ImmutableCollection::create($documentPrintingConfigurations)
                    ->first(fn(DocumentPrintingConfigEntity $config) => $config->getDocumentTypeId() === $invoiceDocumentType->getId());

                $deliveryNotePrintingConfig = ImmutableCollection::create($documentPrintingConfigurations)
                    ->first(fn(DocumentPrintingConfigEntity $config) => $config->getDocumentTypeId() === $deliveryNoteDocumentType->getId());

                $otherPrintingConfigs = ImmutableCollection::create($documentPrintingConfigurations)
                    ->filter(fn(DocumentPrintingConfigEntity $config) => $config->getDocumentTypeId() !== $deliveryNoteDocumentType->getId() && $config->getDocumentTypeId() !== $invoiceDocumentType->getId());

                // If a configuration is available, the document will always be printed.
                if ($invoicePrintingConfig !== null) {
                    $this->deliveryDocumentService->appendInvoiceToDelivery($deliveryId, $context);
                }
                if ($deliveryNotePrintingConfig !== null) {
                    $this->deliveryDocumentService->appendDeliveryNoteToDelivery($deliveryId, $context);
                }
                $shippingMethodConfigs = $primaryOrderDelivery->getShippingMethod()->getExtension(
                    'pickwareWmsShippingMethodConfigs',
                );
                if (
                    ImmutableCollection::create($shippingMethodConfigs)->first()?->getCreateEnclosedReturnLabel()
                    && $this->featureFlagService->isActive(CreateEnclosedReturnLabelFeatureFlag::NAME)
                ) {
                    $this->deliveryDocumentService->appendReturnLabelsToDelivery(
                        $deliveryId,
                        Uuid::randomHex(),
                        $context,
                    );
                }
                if ($otherPrintingConfigs->count() > 0) {
                    $this->deliveryDocumentService->appendOtherDocumentsToDelivery(
                        $deliveryId,
                        $otherPrintingConfigs,
                        $context,
                    );
                }
            },
        );
    }

    public function createShipment(
        string $deliveryId,
        string $shipmentId,
        ShipmentBlueprint $shipmentBlueprint,
        Context $context,
    ): void {
        /** @var DeliveryEntity $delivery */
        $delivery = $this->entityManager->getByPrimaryKey(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
            [
                'state',
            ],
        );

        $deliveryState = $delivery->getState()->getTechnicalName();
        $expectedDeliveryStates = [
            DeliveryStateMachine::STATE_DOCUMENTS_CREATED,
            DeliveryStateMachine::STATE_PACKED,
        ];
        if (!in_array($deliveryState, $expectedDeliveryStates, true)) {
            throw PickingProcessException::invalidDeliveryStateForAction(
                $deliveryId,
                $deliveryState,
                $expectedDeliveryStates,
            );
        }

        $this->deliveryShipmentCreation->createShipmentForDelivery(
            deliveryId: $deliveryId,
            shipmentId: $shipmentId,
            shipmentBlueprint: $shipmentBlueprint,
            isReturnShipment: false,
            context: $context,
        );
    }

    public function ship(string $deliveryId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(
            function() use ($deliveryId, $context): void {
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
                    [
                        'order.stateMachineState',
                        'state',
                        'stockContainer.stocks',
                        'parcels.trackingCodes',
                        'pickingPropertyRecords.values',
                    ],
                );

                /** @deprecated calling ship for deliveries in state `documents_created` or `picked` is deprecated */
                if ($delivery->getState()->getTechnicalName() === DeliveryStateMachine::STATE_PICKED) {
                    $this->skipDocumentCreation($deliveryId, $context);
                    $this->markAsPacked($deliveryId, $context);
                    trigger_error('Calling ship for deliveries in state `picked` is deprecated', E_USER_DEPRECATED);
                } elseif ($delivery->getState()->getTechnicalName() === DeliveryStateMachine::STATE_DOCUMENTS_CREATED) {
                    $this->markAsPacked($deliveryId, $context);
                    trigger_error('Calling ship for deliveries in state `documents_created` is deprecated', E_USER_DEPRECATED);
                }

                /** @var StateMachineState $orderState variable cannot be null here */
                $orderState = $delivery->getOrder()->getStateMachineState();
                if ($orderState->getTechnicalName() === OrderStates::STATE_CANCELLED) {
                    throw PickingProcessException::orderWasCancelled(
                        $delivery->getOrder()->getOrderNumber(),
                        $delivery->getOrder()->getId(),
                    );
                }

                $this->deliveryStateTransitionService->tryDeliveryStateTransition(
                    $deliveryId,
                    DeliveryStateMachine::TRANSITION_SHIP,
                    $context,
                );

                $stockContainer = $delivery->getStockContainer();
                if (!$stockContainer) {
                    // This cannot really happen in the normal life cycle of a picking process. We still can throw this
                    // exception here to be sure.
                    throw PickingProcessException::noStockContainerAssignedForDelivery($deliveryId);
                }

                $trackingCodes = ImmutableCollection::create($delivery->getParcels())
                    ->flatMap(
                        fn(DeliveryParcelEntity $parcel) => ImmutableCollection::create($parcel->getTrackingCodes()
                            ->filter(fn(TrackingCodeEntity $trackingCodeEntity) => !($trackingCodeEntity->getMetaInformation()['cancelled'] ?? false))
                            ->map(
                                fn(TrackingCodeEntity $trackingCodeEntity) => new TrackingCode(
                                    $trackingCodeEntity->getTrackingCode(),
                                    $trackingCodeEntity->getTrackingUrl(),
                                ),
                            )),
                    )
                    ->asArray();

                try {
                    $this->orderParcelService->shipParcelForOrder(
                        $stockContainer->getStocks()->getProductQuantityLocations(),
                        $delivery->getOrder()->getId(),
                        $trackingCodes,
                        $context,
                        array_values($delivery->getPickingPropertyRecords()->map(
                            fn(PickingPropertyDeliveryRecordEntity $record) => new PickingPropertyRecord(
                                $record->getProductId(),
                                $record->getProductSnapshot(),
                                array_values($record->getValues()->map(
                                    fn(PickingPropertyDeliveryRecordValueEntity $value) => new PickingPropertyRecordValue(
                                        $value->getName(),
                                        $value->getValue(),
                                    ),
                                )),
                            ),
                        )),
                    );
                } catch (OrderParcelException $exception) {
                    // Rethrow the error so it is converted into an HTTP 400 error in the controller.
                    throw new PickingProcessException(new JsonApiErrors([$exception->serializeToJsonApiError()]));
                }

                $this->entityManager->update(
                    DeliveryParcelDefinition::class,
                    ImmutableCollection::create($delivery->getParcels())
                        ->map(
                            fn(DeliveryParcelEntity $parcel) => [
                                'id' => $parcel->getId(),
                                'shipped' => true,
                            ],
                        )
                        ->asArray(),
                    $context,
                );

                $this->pickingProcessCleanupService->removeNumbersFromUnusedStockContainersOfPickingProcess(
                    $delivery->getPickingProcessId(),
                    $context,
                );
            },
        );
    }

    public function cancel(string $deliveryId, Context $context, StockReversionAction $stockReversionAction): void
    {
        $this->entityManager->runInTransactionWithRetry(
            function() use ($deliveryId, $context, $stockReversionAction): void {
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
                    [
                        'stockContainer',
                    ],
                );

                if ($this->cancelAndCheckStockContainerForStock($deliveryId, $context)) {
                    switch ($stockReversionAction) {
                        case StockReversionAction::CreateStockingProcess:
                            $this->stockingProcessService->createDeferredStockingProcess(
                                $delivery->getStockContainer()->getWarehouseId(),
                                [
                                    [
                                        'stockContainer' => [
                                            'id' => $delivery->getStockContainerId(),
                                        ],
                                    ],
                                ],
                                $context,
                            );
                            break;
                        case StockReversionAction::StockToUnknownLocation:
                            $this->stockContainerClearingService->putStockInStockContainersToUnknownLocationInWarehouse(
                                [$delivery->getStockContainerId()],
                                $delivery->getStockContainer()->getWarehouseId(),
                                $context,
                            );
                            break;
                    }
                }

                $this->pickingProcessCleanupService->removeNumbersFromUnusedStockContainersOfPickingProcess(
                    $delivery->getPickingProcessId(),
                    $context,
                );
            },
        );
    }

    /**
     * @return bool True, if the delivery has a stock container that contains stock. False otherwise.
     */
    public function cancelAndCheckStockContainerForStock(string $deliveryId, Context $context): bool
    {
        return $this->entityManager->runInTransactionWithRetry(
            function() use ($deliveryId, $context): bool {
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
                    [
                        'state',
                        'stockContainer',
                        'stockContainer.stocks',
                    ],
                );

                $this->deliveryStateTransitionService->tryDeliveryStateTransition(
                    $deliveryId,
                    DeliveryStateMachine::TRANSITION_CANCEL,
                    $context,
                );
                $stockContainer = $delivery->getStockContainer();

                return $stockContainer && $stockContainer->getStocks()->count() > 0;
            },
        );
    }

    public function skipDocumentCreation(string $deliveryId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(
            function() use ($deliveryId, $context): void {
                $this->entityManager->lockPessimistically(
                    DeliveryDefinition::class,
                    ['id' => $deliveryId],
                    $context,
                );

                $this->deliveryStateTransitionService->tryDeliveryStateTransition(
                    $deliveryId,
                    DeliveryStateMachine::TRANSITION_CREATE_DOCUMENTS,
                    $context,
                );
            },
        );
    }

    public function markAsPacked(string $deliveryId, Context $context): void
    {
        $this->deliveryStateTransitionService->tryDeliveryStateTransition(
            $deliveryId,
            DeliveryStateMachine::TRANSITION_PACK,
            $context,
        );
    }

    public function completeDelivery(string $deliveryId, Context $context): void
    {
        /** @var DeliveryEntity $delivery */
        $delivery = $this->entityManager->getByPrimaryKey(
            DeliveryDefinition::class,
            $deliveryId,
            $context,
            [
                'state',
                'stockContainer.stocks',
                'pickingProcess.state',
            ],
        );

        if ($delivery->getState()->getTechnicalName() == DeliveryStateMachine::STATE_PICKED) {
            return;
        }

        if ($delivery->getStockContainer()->getStocks()->count() === 0) {
            throw PickingProcessException::deliveryCannotBeCompletedWithoutStock($deliveryId);
        }

        $this->deliveryStateTransitionService->tryDeliveryStateTransition(
            $deliveryId,
            DeliveryStateMachine::TRANSITION_COMPLETE,
            $context,
        );

        if (
            in_array(
                $delivery->getPickingProcess()->getPickingMode(),
                [
                    PickingProcessDefinition::PICKING_MODE_PRE_COLLECTED_BATCH_PICKING,
                    PickingProcessDefinition::PICKING_MODE_SINGLE_ITEM_ORDERS_PICKING,
                ],
                true,
            )
        ) {
            // Patches the "Complete" event written for the given delivery by updating its user and timestamp to match the
            // "Complete" event written for the corresponding picking process. This is necessary, since we already consider the
            // delivery to be picked when all products are picked into the pre-collecting stock container, but the delivery is
            // only transitioned to the "Picked" state (with the transition "complete") when all products have been picked into
            // the delivery (since we only then know which deliveries of that picking process are actually picked).
            $this->deliveryLifecycleEventService->patchDeliveryEventWithPickingProcessCompleteEvent(
                $delivery->getPickingProcess()->getId(),
                $delivery->getId(),
                DeliveryLifecycleEventType::Complete,
                $context,
            );
        }
    }
}
