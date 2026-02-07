<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt\Subscriber;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptStateMachine;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemDefinition;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptLineItemEntity;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptType;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderLineItemEntity;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderEntity;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemEntity;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\StateTransitionService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\StateMachine\Transition;

/**
 * This service is used to update the state of goods receipt origins (supplier orders and return orders) based on the state of
 * the goods receipt line items which reference them. It should be called whenever any value which is relevant for the state
 * of the goods receipt origins changes.
 *
 * @see GoodsReceiptOriginStateUpdateSubscriber
 */
class GoodsReceiptOriginStateUpdateService
{
    public function __construct(
        private readonly StateTransitionService $stateTransitionService,
        private readonly EntityManager $entityManager,
        private readonly GoodsReceiptOriginReceivedService $goodsReceiptOriginReceivedService,
    ) {}

    /**
     * @param array<string> $goodsReceiptLineItemIds
     */
    public function updateGoodsReceiptOriginState(array $goodsReceiptLineItemIds, Context $context): void
    {
        /** @var ImmutableCollection<GoodsReceiptLineItemEntity> $goodsReceiptLineItems */
        $goodsReceiptLineItems = ImmutableCollection::create($this->entityManager->findBy(
            GoodsReceiptLineItemDefinition::class,
            ['id' => $goodsReceiptLineItemIds],
            $context,
            [
                'goodsReceipt.state',
                ...ImmutableCollection::create(GoodsReceiptType::cases())
                    ->compactMap(
                        fn(GoodsReceiptType $goodsReceiptType) => $goodsReceiptType->getGoodsReceiptLineItemOriginPropertyName(),
                    )
                    ->flatMap(fn(?string $propertyName) => [
                        $propertyName . '.lineItems',
                        $propertyName . '.state',
                        // Is needed because the event might not contain all line items
                        $propertyName . '.goodsReceiptLineItems.goodsReceipt.state',
                    ]),
            ],
        ));
        $goodsReceiptOrigins = $goodsReceiptLineItems
            ->compactMap(function(GoodsReceiptLineItemEntity $lineItem) {
                $propertyName = $lineItem->getGoodsReceipt()->getType()->getGoodsReceiptLineItemOriginPropertyName();
                if ($propertyName === null) {
                    return null;
                }

                return $lineItem->get($propertyName);
            })
            ->reduce([], function($carry, SupplierOrderEntity|ReturnOrderEntity $origin): array {
                $carry[$origin->getId()] ??= $origin;

                return $carry;
            });

        /** @var SupplierOrderEntity|ReturnOrderEntity $goodsReceiptOrigin */
        foreach ($goodsReceiptOrigins as $goodsReceiptOrigin) {
            $type = GoodsReceiptType::fromOriginEntity($goodsReceiptOrigin);

            if ($type->isOriginStateFinal($goodsReceiptOrigin->getState()->getTechnicalName())) {
                // Do not change state if the current state is considered final and should not be changed by this subscriber
                continue;
            }

            if (!$this->isRelevantForStateUpdate($goodsReceiptOrigin)) {
                // Do not calculate state transitions if the goods receipt does not have any relevant line items
                continue;
            }

            $this->goodsReceiptOriginReceivedService->markOriginLineItemsAsFullyReceived(
                $type,
                $this->getFullyReceivedLineItemIds($goodsReceiptOrigin),
                $context,
            );

            // Only update origin state when at least one goods receipt is approved or in a later state
            if (!$this->hasApprovedOrLaterGoodsReceipts($goodsReceiptOrigin)) {
                // Do not change state if all goods receipts are still in created state
                continue;
            }

            if ($this->isFullyReceived($goodsReceiptOrigin)) {
                $this->stateTransitionService->executeStateTransitionIfNotAlreadyInTargetState(
                    new Transition(
                        $type->getOriginEntityName(),
                        $goodsReceiptOrigin->getId(),
                        $type->getOriginReceivedStateTransitionName(),
                        'stateId',
                    ),
                    $context,
                );
            } else {
                $this->stateTransitionService->executeStateTransitionIfNotAlreadyInTargetState(
                    new Transition(
                        $type->getOriginEntityName(),
                        $goodsReceiptOrigin->getId(),
                        $type->getOriginPartiallyReceivedStateTransitionName(),
                        'stateId',
                    ),
                    $context,
                );
            }
        }
    }

    private function isRelevantForStateUpdate(SupplierOrderEntity|ReturnOrderEntity $goodsReceiptOrigin): bool
    {
        return ImmutableCollection::create($goodsReceiptOrigin->getGoodsReceiptLineItems())
            ->filter(fn(GoodsReceiptLineItemEntity $lineItem) => $lineItem->getProductId() !== null)
            ->filter(fn(GoodsReceiptLineItemEntity $lineItem) => $lineItem->getQuantity() > 0)
            ->count() > 0;
    }

    private function isFullyReceived(SupplierOrderEntity|ReturnOrderEntity $goodsReceiptOrigin): bool
    {
        $goodsReceiptLineItems = ImmutableCollection::create($goodsReceiptOrigin->getGoodsReceiptLineItems())
            ->filter(function(GoodsReceiptLineItemEntity $lineItem): bool {
                $stateTechnicalName = $lineItem->getGoodsReceipt()->getState()->getTechnicalName();

                return $stateTechnicalName !== GoodsReceiptStateMachine::STATE_CREATED;
            });

        return $this->calculateNotReceivedProductQuantities(
            $goodsReceiptLineItems,
            ImmutableCollection::create($goodsReceiptOrigin->getLineItems()->getElements()),
        )->count() === 0;
    }

    /**
     * @return ImmutableCollection<string>
     */
    private function getFullyReceivedLineItemIds(SupplierOrderEntity|ReturnOrderEntity $goodsReceiptOrigin): ImmutableCollection
    {
        /** @var EntityCollection<SupplierOrderLineItemEntity|ReturnOrderLineItemEntity> $goodsReceiptOriginLineItems */
        $goodsReceiptOriginLineItems = $goodsReceiptOrigin->getLineItems();
        $notReceivedProductQuantities = $this->calculateNotReceivedProductQuantities(
            ImmutableCollection::create($goodsReceiptOrigin->getGoodsReceiptLineItems()),
            ImmutableCollection::create($goodsReceiptOriginLineItems->getElements()),
        );

        return ImmutableCollection::create($goodsReceiptOriginLineItems->getElements())
            ->filter(
                fn(SupplierOrderLineItemEntity|ReturnOrderLineItemEntity $lineItem) => !$notReceivedProductQuantities
                    ->containsElementSatisfying(fn(ProductQuantity $productQuantity) => $productQuantity->getProductId() === $lineItem->getProductId()),
            )->map(fn(SupplierOrderLineItemEntity|ReturnOrderLineItemEntity $lineItem) => $lineItem->getId());
    }

    /**
     * @param ImmutableCollection<GoodsReceiptLineItemEntity> $goodsReceiptLineItems
     * @param ImmutableCollection<SupplierOrderLineItemEntity|ReturnOrderLineItemEntity> $goodsReceiptOriginLineItems
     */
    private function calculateNotReceivedProductQuantities(
        ImmutableCollection $goodsReceiptLineItems,
        ImmutableCollection $goodsReceiptOriginLineItems,
    ): ProductQuantityImmutableCollection {
        $goodsReceiptLineItems = $goodsReceiptLineItems
            ->filter(fn(GoodsReceiptLineItemEntity $lineItem) => $lineItem->getProductId() !== null);

        $assignedProductQuantities = $goodsReceiptLineItems
            ->map(
                fn(GoodsReceiptLineItemEntity $lineItem) => new ProductQuantity(
                    productId: $lineItem->getProductId(),
                    quantity: $lineItem->getQuantity(),
                ),
                returnType: ProductQuantityImmutableCollection::class,
            )
            ->groupByProductId();

        $originProductQuantities = $goodsReceiptOriginLineItems
            ->filter(fn(SupplierOrderLineItemEntity|ReturnOrderLineItemEntity $lineItem) => GoodsReceiptType::getOriginLineItemIsProductType($lineItem))
            ->map(
                fn(SupplierOrderLineItemEntity|ReturnOrderLineItemEntity $lineItem) => new ProductQuantity(
                    productId: $lineItem->getProductId(),
                    quantity: $lineItem->getQuantity(),
                ),
                returnType: ProductQuantityImmutableCollection::class,
            )
            ->groupByProductId();

        return $originProductQuantities
            ->subtract($assignedProductQuantities)
            ->filter(fn(ProductQuantity $productQuantity) => $productQuantity->getQuantity() > 0);
    }

    private function hasApprovedOrLaterGoodsReceipts(SupplierOrderEntity|ReturnOrderEntity $goodsReceiptOrigin): bool
    {
        return ImmutableCollection::create($goodsReceiptOrigin->getGoodsReceiptLineItems())
            ->filter(fn(GoodsReceiptLineItemEntity $lineItem) => $lineItem->getProductId() !== null)
            ->filter(fn(GoodsReceiptLineItemEntity $lineItem) => $lineItem->getQuantity() > 0)
            ->filter(function(GoodsReceiptLineItemEntity $lineItem): bool {
                $stateTechnicalName = $lineItem->getGoodsReceipt()->getState()->getTechnicalName();

                return $stateTechnicalName !== GoodsReceiptStateMachine::STATE_CREATED;
            })
            ->count() > 0;
    }
}
