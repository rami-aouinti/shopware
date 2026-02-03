<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt;

use InvalidArgumentException;
use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\InstallationLibrary\StateMachine\StateMachineState;
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Batch\BatchManagementDevFeatureFlag;
use Pickware\PickwareErpStarter\GoodsReceipt\Event\GoodsReceiptCompletedEvent;
use Pickware\PickwareErpStarter\GoodsReceipt\FeatureFlags\GoodsReceiptForReturnOrderDevFeatureFlag;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptEntity;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDestinationAssignmentSource;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemEntity;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptType;
use Pickware\PickwareErpStarter\GoodsReceipt\Stocking\GoodsReceiptStockDestinationAssignmentService;
use Pickware\PickwareErpStarter\GoodsReceipt\Stocking\ProductBatchCountingMap;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemEntity;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderCreationService;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderLineItemService;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderPriceCalculationService;
use Pickware\PickwareErpStarter\Stock\Model\SpecialStockLocationDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovement;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\Stocking\StockingRequest;
use Pickware\PickwareErpStarter\Stocking\StockingStrategy;
use Pickware\PickwareErpStarter\StockValuation\Model\ReportDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderEntity;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseDefinition;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration\EntitySnapshotService;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\StateTransitionService;
use RuntimeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class GoodsReceiptService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EntitySnapshotService $entitySnapshotService,
        private readonly StockMovementService $stockMovementService,
        private readonly StateTransitionService $stateTransitionService,
        private readonly StockingStrategy $stockingStrategy,
        private readonly ReturnOrderCreationService $returnOrderCreationService,
        private readonly ReturnOrderLineItemService $returnOrderLineItemService,
        private readonly ReturnOrderPriceCalculationService $returnOrderPriceCalculationService,
        private readonly GoodsReceiptStockDestinationAssignmentService $stockDestinationAssignmentService,
        private readonly GoodsReceiptStockMovementCalculationService $stockMovementCalculationService,
        private readonly GoodsReceiptPriceCalculationService $priceCalculationService,
        private readonly FeatureFlagService $featureFlagService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function approve(string $goodsReceiptId, Context $context): void
    {
        /** @var GoodsReceiptEntity $goodsReceipt */
        $goodsReceipt = $this->entityManager->getByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptId,
            $context,
            [
                'destinationStockMovements',
                'state',
                'lineItems.returnOrder',
            ],
        );

        $this->tryGoodsReceiptStateTransition($goodsReceipt, GoodsReceiptStateMachine::TRANSITION_APPROVE, $context);
        $this->stockDestinationAssignmentService->reassignGoodsReceiptStockDestinations($goodsReceiptId, $context);
        switch ($goodsReceipt->getType()) {
            case GoodsReceiptType::Customer:
                $this->moveStockFromOrdersIntoGoodsReceipt(
                    $goodsReceipt,
                    $context,
                );
                break;
            case GoodsReceiptType::Supplier:
            case GoodsReceiptType::Free:
                $this->moveStockIntoGoodsReceipt($goodsReceipt, $context);
                break;
            default:
                throw new RuntimeException('Unknown goods receipt type');
        }
    }

    private function moveStockIntoGoodsReceipt(GoodsReceiptEntity $goodsReceipt, Context $context): void
    {
        $stockMovements = ImmutableCollection::create($goodsReceipt->getLineItems())
            ->filter(fn(GoodsReceiptLineItemEntity $lineItem) => $lineItem->getProductId() !== null)
            ->map(
                fn(GoodsReceiptLineItemEntity $lineItem) => new GoodsReceiptStockItem(
                    productId: $lineItem->getProductId(),
                    batchId: $lineItem->getBatchId(),
                    quantity: $lineItem->getQuantity(),
                    orderId: null,
                ),
                GoodsReceiptStockItemCollection::class,
            )
            ->collapse()
            ->filter(fn(GoodsReceiptStockItem $productQuantity) => $productQuantity->getQuantity() !== 0)
            ->map(fn(GoodsReceiptStockItem $productQuantity) => $productQuantity->createStockMovement(
                source: StockLocationReference::unknown(),
                destination: StockLocationReference::goodsReceipt($goodsReceipt->getId()),
                context: $context,
            ))
            ->asArray();

        $this->stockMovementService->moveStock($stockMovements, $context);
    }

    private function moveStockFromOrdersIntoGoodsReceipt(
        GoodsReceiptEntity $goodsReceipt,
        Context $context,
    ): void {
        $stockMovements = $this->stockMovementCalculationService->calculateStockMovementsFromOrdersIntoGoodsReceipt(
            $goodsReceipt->getId(),
            ImmutableCollection::create($goodsReceipt->getLineItems())
                ->map(
                    fn(GoodsReceiptLineItemEntity $lineItem) => new GoodsReceiptStockItem(
                        productId: $lineItem->getProductId(),
                        batchId: $lineItem->getBatchId(),
                        quantity: $lineItem->getQuantity(),
                        orderId: $lineItem->getReturnOrder()?->getOrderId(),
                    ),
                    GoodsReceiptStockItemCollection::class,
                )
                ->collapse(),
            $context,
        );

        if (count($stockMovements) > 0) {
            $this->stockMovementService->moveStock($stockMovements, $context);
        }
    }

    /**
     * Moves all stock currently in the goods receipt into the warehouse. When the batch-management dev feature flag is
     * active, it tries to move stock according to the goods receipt line item destinations. Currently, this function
     * does not regard any stock that might have already been moved into the warehouse by other means previously. Any
     * stock that does not match a line item is moved using the stocking strategy.
     *
     * Otherwise, this function uses a stocking strategy directly, without considering the line items at all.
     */
    public function moveStockIntoWarehouse(string $goodsReceiptId, string $warehouseId, Context $context): void
    {
        /** @var GoodsReceiptEntity $goodsReceipt */
        $goodsReceipt = $this->entityManager->getByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptId,
            $context,
            [
                'lineItems',
                'stocks.batchMappings',
                'state',
            ],
        );

        if ($goodsReceipt->getState()->getTechnicalName() === GoodsReceiptStateMachine::STATE_APPROVED) {
            $this->tryGoodsReceiptStateTransition($goodsReceipt, GoodsReceiptStateMachine::TRANSITION_START, $context);
            trigger_error(
                'Calling the `moveStockIntoWarehouse` method in state `approved` is deprecated. Call `startStocking`' .
                ' before calling this method. This warning will throw an exception in v.4.0.0.',
                E_USER_DEPRECATED,
            );
        }

        if ($goodsReceipt->getState()->getTechnicalName() !== GoodsReceiptStateMachine::STATE_IN_PROGRESS) {
            throw new GoodsReceiptException(GoodsReceiptError::invalidGoodsReceiptStateForAction(
                goodsReceiptId: $goodsReceipt->getId(),
                actualStateName: $goodsReceipt->getState()->getTechnicalName(),
                expectedStateNames: [GoodsReceiptStateMachine::STATE_IN_PROGRESS],
            ));
        }

        if ($this->featureFlagService->isActive(BatchManagementDevFeatureFlag::NAME)) {
            $stockToMove = ProductBatchCountingMap::fromStockCollection($goodsReceipt->getStocks());
            $stockMovements = [];
            // Move as much stock as possible, according to the line item destinations.
            foreach ($goodsReceipt->getLineItems() as $lineItem) {
                if ($lineItem->getProductId() === null || $lineItem->getDestinationAssignmentSource() === GoodsReceiptLineItemDestinationAssignmentSource::Unset) {
                    continue;
                }

                $quantityInGoodsReceipt = $stockToMove->get($lineItem->getProductId(), $lineItem->getBatchId());
                $quantityToMove = min($lineItem->getQuantity(), $quantityInGoodsReceipt);
                if ($quantityToMove > 0) {
                    $stockMovements[] = $this->createStockMovementForLineItem($lineItem, $warehouseId, $quantityToMove, $context);
                    $stockToMove->add($lineItem->getProductId(), $lineItem->getBatchId(), -$quantityToMove);
                }
            }

            // Move any remaining stock using the stocking strategy.
            if (!$stockToMove->isEmpty()) {
                $stockingRequest = new StockingRequest(
                    productQuantities: $stockToMove->toProductQuantityCollection(),
                    stockArea: StockArea::warehouse($warehouseId),
                );
                $stockMovements = [
                    ...$stockMovements,
                    ...$this->stockingStrategy
                        ->calculateStockingSolution($stockingRequest, $context)
                        ->createStockMovementsWithSource(StockLocationReference::goodsReceipt($goodsReceiptId), [
                            'userId' => ContextExtension::getUserId($context),
                        ]),
                ];
            }
        } else {
            $stockingRequest = new StockingRequest(
                productQuantities: $goodsReceipt->getStocks()->getProductQuantityLocations()->groupByProductId(),
                stockArea: StockArea::warehouse($warehouseId),
            );
            $stockMovements = $this->stockingStrategy
                ->calculateStockingSolution($stockingRequest, $context)
                ->createStockMovementsWithSource(StockLocationReference::goodsReceipt($goodsReceiptId), [
                    'userId' => ContextExtension::getUserId($context),
                ]);
        }

        if (count($stockMovements) > 0) {
            $this->stockMovementService->moveStock($stockMovements, $context);
        }
    }

    private function createStockMovementForLineItem(
        GoodsReceiptLineItemEntity $lineItem,
        string $warehouseId,
        int $quantity,
        Context $context,
    ): StockMovement {
        $stockMovementData = [
            'productId' => $lineItem->getProductId(),
            'quantity' => $quantity,
            'source' => StockLocationReference::goodsReceipt($lineItem->getGoodsReceiptId()),
            'userId' => ContextExtension::getUserId($context),
        ];
        if ($lineItem->getDestinationBinLocationId()) {
            $stockMovementData['destination'] = StockLocationReference::binLocation($lineItem->getDestinationBinLocationId());
        } else {
            $stockMovementData['destination'] = StockLocationReference::warehouse($warehouseId);
        }
        if ($lineItem->getBatchId()) {
            $stockMovementData['batches'] = new CountingMap([$lineItem->getBatchId() => $quantity]);
        }

        return StockMovement::create($stockMovementData);
    }

    public function startStocking(string $goodsReceiptId, Context $context): void
    {
        /** @var GoodsReceiptEntity $goodsReceipt */
        $goodsReceipt = $this->entityManager->getByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptId,
            $context,
            ['state'],
        );
        $this->tryGoodsReceiptStateTransition($goodsReceipt, GoodsReceiptStateMachine::TRANSITION_START, $context);
    }

    public function complete(string $goodsReceiptId, Context $context): void
    {
        /** @var GoodsReceiptEntity $goodsReceipt */
        $goodsReceipt = $this->entityManager->getByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptId,
            $context,
            [
                'stocks',
                'state',
            ],
        );

        if ($goodsReceipt->getStocks()->count() !== 0) {
            throw new GoodsReceiptException(
                GoodsReceiptError::goodsReceiptStillContainsStock(goodsReceiptId: $goodsReceiptId),
            );
        }

        $this->tryGoodsReceiptStateTransition($goodsReceipt, GoodsReceiptStateMachine::TRANSITION_COMPLETE, $context);

        $this->eventDispatcher->dispatch(
            new GoodsReceiptCompletedEvent(
                goodsReceiptId: $goodsReceipt->getId(),
                context: $context,
            ),
        );
    }

    public function reassignLineItemsToSources(string $goodsReceiptId, Context $context): void
    {
        $this->reassignLineItemsToOrigins($goodsReceiptId, $context);
    }

    /**
     * Re-assigns all line items of the given goods receipt to the origins of the goods receipt (which e.g. can be
     * supplier orders or other entities, depending on the origin of the goods receipt).
     *
     * Existing line items are deleted, recreated and re-assigned (re-distributed) to the origins with priority to the
     * oldest origin (oldest supplier order).
     */
    public function reassignLineItemsToOrigins(string $goodsReceiptId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($context, $goodsReceiptId): void {
            /** @var GoodsReceiptEntity $goodsReceipt */
            $goodsReceipt = $this->entityManager->getByPrimaryKey(
                GoodsReceiptDefinition::class,
                $goodsReceiptId,
                $context,
                [
                    'state',
                    'lineItems',
                    'supplierOrders.lineItems.supplierOrder.goodsReceiptLineItems',
                    'returnOrders.lineItems.returnOrder.goodsReceiptLineItems',
                ],
            );

            // If it is a free goods receipt, it does not have any sources that line items can be reassigned to
            if ($goodsReceipt->getType() === GoodsReceiptType::Free) {
                return;
            }

            if ($goodsReceipt->getState()->getTechnicalName() !== GoodsReceiptStateMachine::STATE_CREATED) {
                throw new GoodsReceiptException(GoodsReceiptError::invalidGoodsReceiptStateForAction(
                    goodsReceiptId: $goodsReceipt->getId(),
                    actualStateName: $goodsReceipt->getState()->getTechnicalName(),
                    expectedStateNames: [GoodsReceiptStateMachine::STATE_CREATED],
                ));
            }

            $origins = $goodsReceipt->getType()->getOriginsFromGoodsReceipt($goodsReceipt);
            /** @var ImmutableCollection<SupplierOrderEntity|ReturnOrderEntity> $sortedOrigins */
            $sortedOrigins = ImmutableCollection::create($origins)
                ->sorted(fn(Entity $lhs, Entity $rhs) => $lhs->getCreatedAt() <=> $rhs->getCreatedAt());
            $originLineItems = $sortedOrigins
                ->flatMap(fn(SupplierOrderEntity|ReturnOrderEntity $entity) => $entity->getLineItems()->getElements())
                ->filter(fn(SupplierOrderLineItemEntity|ReturnOrderLineItemEntity $item) => $item->getProductId() !== null);

            $goodsReceiptLineItems = ImmutableCollection::create($goodsReceipt->getLineItems())
                ->filter(fn(GoodsReceiptLineItemEntity $item) => $item->getProductId() !== null);

            $this->entityManager->delete(
                GoodsReceiptLineItemDefinition::class,
                $goodsReceiptLineItems->map(fn(GoodsReceiptLineItemEntity $item) => $item->getId())->asArray(),
                $context,
            );

            // Merge line items that have the same product and batch IDs into one item to distribute.
            $productQuantitiesToDistribute = [];
            foreach ($goodsReceiptLineItems as $goodsReceiptLineItem) {
                $key = $goodsReceiptLineItem->getProductId() . $goodsReceiptLineItem->getBatchId();
                $productQuantitiesToDistribute[$key] ??= [
                    'productId' => $goodsReceiptLineItem->getProductId(),
                    'batchId' => $goodsReceiptLineItem->getBatchId(),
                    'productSnapshot' => [],
                    'quantity' => 0,
                ];
                $productQuantitiesToDistribute[$key]['productSnapshot'] = $goodsReceiptLineItem->getProductSnapshot();
                $productQuantitiesToDistribute[$key]['quantity'] += $goodsReceiptLineItem->getQuantity();
            }

            $lineItemPayloads = [];
            foreach ($productQuantitiesToDistribute as $item) {
                $productId = $item['productId'];
                $batchId = $item['batchId'];
                $quantity = $item['quantity'];
                $productSnapshot = $item['productSnapshot'];

                while ($quantity > 0) {
                    /** @var SupplierOrderLineItemEntity|ReturnOrderLineItemEntity $matchingOriginLineItem */
                    $matchingOriginLineItem = $originLineItems->first(
                        fn(SupplierOrderLineItemEntity|ReturnOrderLineItemEntity $lineItem) => $lineItem->getProductId() === $productId && $lineItem->getQuantity() > 0,
                    );
                    if ($matchingOriginLineItem === null) {
                        $lineItemPayloads[] = [
                            'productId' => $productId,
                            'batchId' => $batchId,
                            'productSnapshot' => $productSnapshot,
                            'quantity' => $quantity,
                        ];
                        break;
                    }

                    // Find the quantity of the product already assigned to a goods receipt of the origin.
                    /** @var SupplierOrderEntity|ReturnOrderEntity|null $goodsReceiptOrigin */
                    $goodsReceiptOrigin = $matchingOriginLineItem->get($goodsReceipt->getType()->getGoodsReceiptLineItemOriginPropertyName());
                    $quantityAlreadyAssignedInGoodsReceiptsOfOrigin = ImmutableCollection::create($goodsReceiptOrigin?->getGoodsReceiptLineItems() ?? [])
                        ->filter(fn(GoodsReceiptLineItemEntity $lineItem) => $lineItem->getProductId() === $productId)
                        ->map(fn(GoodsReceiptLineItemEntity $lineItem) => $lineItem->getQuantity())
                        ->sum();

                    $openQuantity = max(0, $matchingOriginLineItem->getQuantity() - $quantityAlreadyAssignedInGoodsReceiptsOfOrigin);
                    $quantityToAssign = min($quantity, $openQuantity);
                    $matchingOriginLineItem->setQuantity($openQuantity - $quantityToAssign);
                    if ($quantityToAssign === 0) {
                        continue;
                    }
                    $quantity -= $quantityToAssign;

                    $payload = [
                        'productId' => $productId,
                        'batchId' => $batchId,
                        'productSnapshot' => $productSnapshot,
                        'quantity' => $quantityToAssign,
                        'priceDefinition' => $matchingOriginLineItem->getPriceDefinition(),
                    ];

                    $goodsReceipt->getType()->setTypeSpecificLineItemPayload($goodsReceipt, $matchingOriginLineItem, $payload);

                    $lineItemPayloads[] = $payload;
                }
            }

            foreach ($lineItemPayloads as &$lineItemPayload) {
                $lineItemPayload['goodsReceiptId'] = $goodsReceiptId;
            }
            unset($lineItemPayload);

            $this->entityManager->create(GoodsReceiptLineItemDefinition::class, $lineItemPayloads, $context);
        });
    }

    /**
     * @deprecated Removed with Pickware ERP 5.0.0 without replacement.
     */
    public function assignReturnReasonsToOriginLineItems(
        string $goodsReceiptId,
        array $returnReasonPayload,
        Context $context,
    ): void {
        /** @var GoodsReceiptEntity $goodsReceipt */
        $goodsReceipt = $this->entityManager->getByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptId,
            $context,
            ['returnOrders'],
        );

        foreach ($returnReasonPayload as &$payload) {
            $payload['reason'] ??= $payload['returnReason'] ?? null;
        }
        unset($payload);

        $returnOrderIds = array_values($goodsReceipt->getReturnOrders()->getIds());
        if (!empty($returnOrderIds)) {
            $this->returnOrderLineItemService->assignReturnReasonsToReturnOrderLineItems(
                returnOrderIds: $returnOrderIds,
                countedReturnedProducts: $returnReasonPayload,
                context: $context,
            );
        }
    }

    private function tryGoodsReceiptStateTransition(
        GoodsReceiptEntity $goodsReceipt,
        string $transitionName,
        Context $context,
    ): void {
        try {
            $this->stateTransitionService->executeStateTransition(
                new Transition(
                    GoodsReceiptDefinition::ENTITY_NAME,
                    $goodsReceipt->getId(),
                    $transitionName,
                    'stateId',
                ),
                $context,
            );
        } catch (IllegalTransitionException $e) {
            $expectedStates = (new GoodsReceiptStateMachine())
                ->getStatesThatAllowTransitionWithName($transitionName);

            throw new GoodsReceiptException(
                GoodsReceiptError::invalidGoodsReceiptStateForAction(
                    $goodsReceipt->getId(),
                    $goodsReceipt->getState()->getTechnicalName(),
                    array_map(fn(StateMachineState $state) => $state->getTechnicalName(), $expectedStates),
                ),
            );
        }
    }

    /**
     * Disposes all stock in the given goods receipt by moving it to the unknown stock location.
     */
    public function disposeRemainingStockInGoodsReceipt(string $goodsReceiptId, Context $context): void
    {
        $this->entityManager->lockPessimistically(
            StockDefinition::class,
            ['goodsReceiptId' => $goodsReceiptId],
            $context,
        );

        /** @var StockCollection $stocksInGoodsReceipt */
        $stocksInGoodsReceipt = $this->entityManager->findBy(
            StockDefinition::class,
            ['goodsReceiptId' => $goodsReceiptId],
            $context,
        );
        $stockMovements = $stocksInGoodsReceipt
            ->getProductQuantities()
            ->map(fn(ProductQuantity $productQuantity) => StockMovement::create([
                'id' => Uuid::randomHex(),
                'productId' => $productQuantity->getProductId(),
                'quantity' => $productQuantity->getQuantity(),
                'source' => StockLocationReference::goodsReceipt($goodsReceiptId),
                'destination' => StockLocationReference::specialStockLocation(
                    SpecialStockLocationDefinition::TECHNICAL_NAME_UNKNOWN,
                ),
            ]));

        $this->stockMovementService->moveStock($stockMovements->asArray(), $context);
    }

    /**
     * @param array<array<string, string>> $returnOrders A list containing elements with either an `id` or an `orderId`.
     */
    public function mapReturnOrdersToGoodsReceipt(string $goodsReceiptId, array $returnOrders, Context $context): void
    {
        /** @var GoodsReceiptEntity $goodsReceipt */
        $goodsReceipt = $this->entityManager->getByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptId,
            $context,
            ['lineItems'],
        );
        if ($goodsReceipt->getType() !== GoodsReceiptType::Customer) {
            throw new InvalidArgumentException('Return orders can only be assigned to goods receipts of type customer.');
        }
        $productsInGoodsReceipt = new ProductQuantityImmutableCollection($goodsReceipt->getLineItems()->map(
            fn(GoodsReceiptLineItemEntity $lineItem) => new ProductQuantity(
                $lineItem->getProductId(),
                $lineItem->getQuantity(),
            ),
        ));

        $orderIds = array_values(array_filter(array_column($returnOrders, 'orderId')));
        $returnOrderIds = array_values(array_filter(array_column($returnOrders, 'id')));

        // If orderIds are provided, a new return order is created for each order. At this point, we do not search for
        // existing return orders, or reduce the quantity of products to return by the already existing return order
        // line items before creating new return orders. However, this behavior might change in the future.
        if (!empty($orderIds)) {
            $newReturnOrderIds = $this->returnOrderCreationService->createReturnOrdersForReturnedProductsInOrders(
                orderIds: $orderIds,
                returnedProducts: $productsInGoodsReceipt,
                context: $context,
            );
            $this->returnOrderPriceCalculationService->recalculateReturnOrders($newReturnOrderIds, $context);

            $returnOrderIds = [
                ...$returnOrderIds,
                ...$newReturnOrderIds,
            ];
        }

        $this->entityManager->update(
            GoodsReceiptDefinition::class,
            [
                [
                    'id' => $goodsReceiptId,
                    'returnOrders' => array_map(fn(string $id) => ['id' => $id], $returnOrderIds),
                ],
            ],
            $context,
        );
    }

    public function getStockValuationReportIdForGoodsReceipt(string $goodsReceiptId, Context $context): ?string
    {
        /** @var string[] $reportIds */
        $reportIds = $this->entityManager->findIdsBy(
            ReportDefinition::class,
            (new Criteria())
                ->addFilter(new EqualsFilter('preview', false))
                ->addFilter(new EqualsFilter('rows.purchases.goodsReceiptLineItem.goodsReceiptId', $goodsReceiptId))
                ->setLimit(1),
            $context,
        );

        return $reportIds[0] ?? null;
    }

    public function updateGoodsReceiptWarehouse(string $goodsReceiptId, string $warehouseId, Context $context): void
    {
        /** @var GoodsReceiptEntity $goodsReceipt */
        $goodsReceipt = $this->entityManager->getByPrimaryKey(
            GoodsReceiptDefinition::class,
            $goodsReceiptId,
            $context,
            ['state'],
        );

        if ($this->getStockValuationReportIdForGoodsReceipt($goodsReceiptId, $context) !== null) {
            throw new GoodsReceiptException(
                GoodsReceiptError::goodsReceiptContainedInStockValuationReport(goodsReceiptId: $goodsReceipt->getId()),
            );
        }

        $warehouseSnapshot = $this->entitySnapshotService->generateSnapshots(
            WarehouseDefinition::class,
            [$warehouseId],
            $context,
        )[$warehouseId];

        $this->entityManager->update(
            GoodsReceiptDefinition::class,
            [
                [
                    'id' => $goodsReceiptId,
                    'warehouseId' => $warehouseId,
                    'warehouseSnapshot' => $warehouseSnapshot,
                ],
            ],
            $context,
        );
    }

    /**
     * @param positive-int $newLineItemQuantity
     */
    public function splitGoodsReceiptLineItem(string $lineItemId, int $newLineItemQuantity, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($lineItemId, $newLineItemQuantity, $context): void {
            $this->entityManager->lockPessimistically(
                GoodsReceiptDefinition::class,
                ['lineItems.id' => $lineItemId],
                $context,
            );

            /** @var GoodsReceiptLineItemEntity $lineItem */
            $lineItem = $this->entityManager->getByPrimaryKey(
                GoodsReceiptLineItemDefinition::class,
                $lineItemId,
                $context,
                ['goodsReceipt.state'],
            );

            if ($newLineItemQuantity >= $lineItem->getQuantity()) {
                throw new GoodsReceiptException(
                    GoodsReceiptError::goodsReceiptLineItemQuantityInvalid(maxQuantity: $lineItem->getQuantity() - 1),
                );
            }

            if ($lineItem->getGoodsReceipt()->getState()->getTechnicalName() === GoodsReceiptStateMachine::STATE_COMPLETED) {
                throw new GoodsReceiptException(GoodsReceiptError::invalidGoodsReceiptStateForAction(
                    goodsReceiptId: $lineItem->getGoodsReceiptId(),
                    actualStateName: $lineItem->getGoodsReceipt()->getState()->getTechnicalName(),
                    expectedStateNames: [
                        GoodsReceiptStateMachine::STATE_CREATED,
                        GoodsReceiptStateMachine::STATE_APPROVED,
                        GoodsReceiptStateMachine::STATE_IN_PROGRESS,
                    ],
                ));
            }

            $this->entityManager->getRepository(GoodsReceiptLineItemDefinition::class)->clone(
                id: $lineItemId,
                context: $context,
                behavior: new CloneBehavior(
                    overwrites: ['quantity' => $newLineItemQuantity],
                    cloneChildren: false,
                ),
            );
            $this->entityManager->update(
                GoodsReceiptLineItemDefinition::class,
                [
                    [
                        'id' => $lineItemId,
                        'quantity' => $lineItem->getQuantity() - $newLineItemQuantity,
                    ],
                ],
                $context,
            );

            $this->priceCalculationService->recalculateGoodsReceipts([$lineItem->getGoodsReceiptId()], $context);
        });
    }

    /**
     * This method exists to allow feature detection in the pickware-wms plugin.
     */
    public function areGoodsReceiptsForCustomersAvailable(): bool
    {
        // When removing the feature flag, this method should always return true.
        return $this->featureFlagService->isActive(GoodsReceiptForReturnOrderDevFeatureFlag::NAME);
    }

    /**
     * This method exists to allow feature detection in the pickware-wms plugin.
     */
    public function areGoodsReceiptAdditionalInformationAvailable(): bool
    {
        return true;
    }
}
