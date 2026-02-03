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

use BadMethodCallException;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerDefinition;
use Pickware\PickwareWms\Delivery\DeliveryStateTransitionService;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessEntity;
use Shopware\Core\Framework\Context;

class PickingProcessCleanupService
{
    private EntityManager $entityManager;
    private DeliveryStateTransitionService $deliveryStateTransitionService;

    public function __construct(
        EntityManager $entityManager,
        DeliveryStateTransitionService $deliveryStateTransitionService,
    ) {
        $this->entityManager = $entityManager;
        $this->deliveryStateTransitionService = $deliveryStateTransitionService;
    }

    public function removeNumbersFromUnusedStockContainersOfPickingProcess(string $pickingProcessId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($pickingProcessId, $context): void {
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

            $stockContainerUpdatePayloads = [];
            foreach ($pickingProcess->getDeliveries() as $delivery) {
                if (
                    $delivery->getStockContainerId()
                    && in_array($delivery->getState()->getTechnicalName(), DeliveryStateMachine::CONCLUDED_STATES, true)
                ) {
                    $stockContainerUpdatePayloads[] = [
                        'id' => $delivery->getStockContainerId(),
                        'number' => null,
                    ];
                }
            }

            if (
                $pickingProcess->getPreCollectingStockContainerId()
                && in_array($pickingProcess->getState()->getTechnicalName(), PickingProcessStateMachine::CONCLUDED_STATES, true)
            ) {
                $stockContainerUpdatePayloads[] = [
                    'id' => $pickingProcess->getPreCollectingStockContainerId(),
                    'number' => null,
                ];
            }

            if (!empty($stockContainerUpdatePayloads)) {
                $this->entityManager->update(StockContainerDefinition::class, $stockContainerUpdatePayloads, $context);
            }
        });
    }

    public function cancelDeliveriesThatAreNotFulfillableWithPreCollectedStock(string $pickingProcessId, Context $context): void
    {
        /** @var PickingProcessEntity $pickingProcess */
        $pickingProcess = $this->entityManager->getByPrimaryKey(PickingProcessDefinition::class, $pickingProcessId, $context, [
            'preCollectingStockContainer.stocks',
            'deliveries.lineItems',
            'deliveries.order',
        ]);

        $preCollectingStockContainer = $pickingProcess->getPreCollectingStockContainer();
        if ($preCollectingStockContainer === null) {
            throw new BadMethodCallException(
                'This method cannot be called for picking processes without a pre-collecting stock container.',
            );
        }

        $unfulfillableDeliveryIds = [];

        $pickedProductQuantities = [];
        foreach ($preCollectingStockContainer->getStocks() as $stock) {
            $productId = $stock->getProductId();
            $pickedProductQuantities[$productId] = ($pickedProductQuantities[$productId] ?? 0) + $stock->getQuantity();
        }

        $pickingProcess->getDeliveries()->sort(function(DeliveryEntity $a, DeliveryEntity $b): int {
            $orderDateA = $a->getOrder()?->getOrderDate();
            $orderDateB = $b->getOrder()?->getOrderDate();

            return $orderDateA <=> $orderDateB;
        });

        foreach ($pickingProcess->getDeliveries() as $delivery) {
            $lineItems = $delivery->getLineItems();

            $isFulfillable = false;
            foreach ($lineItems as $lineItem) {
                $productId = $lineItem->getProductId();
                if (($pickedProductQuantities[$productId] ?? 0) > 0) {
                    $isFulfillable = true;
                    break;
                }
            }

            if ($isFulfillable) {
                foreach ($lineItems as $lineItem) {
                    $productId = $lineItem->getProductId();
                    $quantityToDeduct = min($pickedProductQuantities[$productId] ?? 0, $lineItem->getQuantity());
                    $pickedProductQuantities[$productId] = ($pickedProductQuantities[$productId] ?? 0) - $quantityToDeduct;
                }
            } else {
                $unfulfillableDeliveryIds[] = $delivery->getId();
            }
        }

        foreach ($unfulfillableDeliveryIds as $deliveryId) {
            $this->deliveryStateTransitionService->tryDeliveryStateTransition(
                $deliveryId,
                DeliveryStateMachine::TRANSITION_CANCEL,
                $context,
            );
        }
    }
}
