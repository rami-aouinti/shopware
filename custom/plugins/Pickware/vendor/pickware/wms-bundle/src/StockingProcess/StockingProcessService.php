<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockingProcess;

use Pickware\DalBundle\EntityManager;
use Pickware\InstallationLibrary\StateMachine\StateMachineState;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptService;
use Pickware\PickwareErpStarter\GoodsReceipt\GoodsReceiptStateMachine;
use Pickware\PickwareErpStarter\GoodsReceipt\Model\GoodsReceiptDefinition;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareWms\Device\Device;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessDefinition;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessEntity;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessLineItemDefinition;
use Pickware\PickwareWms\StockingProcess\Model\StockingProcessLineItemEntity;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\StateTransitionService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Transition;

class StockingProcessService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly StockMovementService $stockMovementService,
        private readonly StockingProcessLineItemCalculator $stockingProcessLineItemCalculator,
        private readonly StateTransitionService $stateTransitionService,
        private readonly GoodsReceiptService $goodsReceiptService,
        private readonly StockingProcessCreation $stockingProcessCreation,
    ) {}

    public function startStockingProcess(string $stockingProcessId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($stockingProcessId, $context): void {
            /** @var StockingProcessEntity $stockingProcess */
            $stockingProcess = $this->entityManager->getByPrimaryKey(
                StockingProcessDefinition::class,
                $stockingProcessId,
                $context,
                [
                    'state',
                    'sources.goodsReceipt.state',
                ],
            );

            $this->tryStateTransition(
                $stockingProcess,
                StockingProcessStateMachine::TRANSITION_START,
                $context,
            );

            // Ensure sources are ready for stocking after state transition attempt to improve error messages.
            $this->ensureSourcesAreReadyForStocking($stockingProcess, $context);

            $this->stockingProcessLineItemCalculator->recalculateStockingProcessLineItems(
                $stockingProcessId,
                $context,
            );
            $this->entityManager->update(
                StockingProcessDefinition::class,
                [
                    [
                        'id' => $stockingProcessId,
                        'userId' => ContextExtension::getUserId($context),
                        'device' => Device::getFromContext($context)->toPayload(),
                    ],
                ],
                $context,
            );
        });
    }

    /**
     * @param array<mixed> $sourcesPayload
     */
    public function createDeferredStockingProcess(string $warehouseId, array $sourcesPayload, Context $context): string
    {
        return $this->entityManager->runInTransactionWithRetry(
            function() use ($warehouseId, $sourcesPayload, $context): string {
                $stockingProcessId = Uuid::randomHex();
                $this->stockingProcessCreation->createStockingProcess(
                    [
                        'id' => $stockingProcessId,
                        'warehouseId' => $warehouseId,
                        'sources' => $sourcesPayload,
                    ],
                    $context,
                );
                $this->startStockingProcess(
                    $stockingProcessId,
                    $context,
                );
                $this->deferStockingProcess(
                    $stockingProcessId,
                    $context,
                );

                return $stockingProcessId;
            },
        );
    }

    private function ensureSourcesAreReadyForStocking(StockingProcessEntity $stockingProcess, Context $context): void
    {
        foreach ($stockingProcess->getSources()->getElements() as $source) {
            $goodsReceipt = $source->getGoodsReceipt();
            if ($goodsReceipt !== null) {
                if ($goodsReceipt->getState()->getTechnicalName() !== GoodsReceiptStateMachine::STATE_APPROVED) {
                    throw StockingProcessException::invalidGoodsReceiptState(
                        number: $goodsReceipt->getNumber(),
                        allowedState: GoodsReceiptStateMachine::STATE_APPROVED,
                        actualState: $goodsReceipt->getState()->getTechnicalName(),
                    );
                }

                $this->stateTransitionService->executeStateTransition(
                    new Transition(
                        GoodsReceiptDefinition::ENTITY_NAME,
                        $goodsReceipt->getId(),
                        GoodsReceiptStateMachine::TRANSITION_START,
                        'stateId',
                    ),
                    $context,
                );
            }
        }
    }

    public function stockItem(
        StockingItem $item,
        Context $context,
    ): void {
        $this->entityManager->runInTransactionWithRetry(
            function() use ($item, $context): void {
                $stockingProcessId = $item->getStockingProcessId();
                $this->entityManager->lockPessimistically(
                    StockingProcessDefinition::class,
                    ['id' => $stockingProcessId],
                    $context,
                );
                /** @var StockingProcessEntity $stockingProcess */
                $stockingProcess = $this->entityManager->findByPrimaryKey(
                    StockingProcessDefinition::class,
                    $stockingProcessId,
                    $context,
                    [
                        'state',
                        'device',
                        'lineItems',
                        'sources.goodsReceipt.stocks',
                        'sources.stockContainer.stocks',
                    ],
                );
                if (!$stockingProcess) {
                    throw StockingProcessException::stockingProcessNotFound($stockingProcessId);
                }

                // Step 1: Ensure stocking privileges
                if ($stockingProcess->getState()->getTechnicalName() !== StockingProcessStateMachine::STATE_IN_PROGRESS) {
                    throw StockingProcessException::invalidStockingProcessState(
                        stockingProcessId: $stockingProcessId,
                        currentStateName: $stockingProcess->getState()->getTechnicalName(),
                        expectedStateNames: [StockingProcessStateMachine::STATE_IN_PROGRESS],
                    );
                }

                self::validateDeviceOwnership($stockingProcess, $context);

                // Step 2: Calculate stock movements from stocking process sources
                $stockMovements = $item->createStockMovementsForSources(
                    sources: $stockingProcess
                        ->getSources()
                        ->getProductQuantityLocations()
                        ->filter(fn(ProductQuantityLocation $stock) => $stock->getProductId() === $item->getProductId()),
                    context: $context,
                );
                $this->stockMovementService->moveStock($stockMovements, $context);

                // Step 3: Calculate corresponding line item changes caused by the stock movements
                $matchingLineItem = ImmutableCollection::create($stockingProcess->getLineItems())->first(
                    fn(StockingProcessLineItemEntity $lineItem) => $lineItem->getProductId() === $item->getProductId()
                        && $lineItem->getStockLocationReference()->equals($item->getDestination()),
                );
                $additionalProductLineItems = ImmutableCollection::create($stockingProcess->getLineItems())->filter(
                    fn(StockingProcessLineItemEntity $lineItem) => $lineItem->getProductId() === $item->getProductId()
                        && !$lineItem->getStockLocationReference()->equals($item->getDestination()),
                );
                $stockedLineItems = array_filter([
                    $matchingLineItem,
                    ...$additionalProductLineItems->asArray(),
                ]);

                $deleteLineItemsPayload = [];
                $updateLineItemsPayload = [];
                $stockedQuantity = $item->getQuantity();

                while ($stockedQuantity > 0) {
                    $lineItem = array_shift($stockedLineItems);
                    if (!$lineItem) {
                        break;
                    }
                    $stockedQuantity -= $lineItem->getQuantity();
                    if ($stockedQuantity >= 0) {
                        $deleteLineItemsPayload[] = $lineItem->getId();
                    } else {
                        $updateLineItemsPayload[] = [
                            'id' => $lineItem->getId(),
                            'quantity' => -1 * $stockedQuantity,
                        ];
                    }
                }

                if (count($deleteLineItemsPayload) > 0) {
                    $this->entityManager->delete(
                        StockingProcessLineItemDefinition::class,
                        $deleteLineItemsPayload,
                        $context,
                    );
                }
                if (count($updateLineItemsPayload) > 0) {
                    $this->entityManager->update(
                        StockingProcessLineItemDefinition::class,
                        $updateLineItemsPayload,
                        $context,
                    );
                }
            },
        );
    }

    public function deferStockingProcess(string $stockingProcessId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($stockingProcessId, $context): void {
            /** @var StockingProcessEntity $stockingProcess */
            $stockingProcess = $this->entityManager->getByPrimaryKey(
                StockingProcessDefinition::class,
                $stockingProcessId,
                $context,
                [
                    'state',
                    'device',
                ],
            );

            $this->tryStateTransition(
                $stockingProcess,
                StockingProcessStateMachine::TRANSITION_DEFER,
                $context,
            );

            // Validate device ownership after the state transition attempt to improve error messaging.
            self::validateDeviceOwnership($stockingProcess, $context);
            $this->entityManager->update(
                StockingProcessDefinition::class,
                [
                    [
                        'id' => $stockingProcessId,
                        'userId' => null,
                        'deviceId' => null,
                    ],
                ],
                $context,
            );
        });
    }

    public function continueStockingProcess(string $stockingProcessId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($stockingProcessId, $context): void {
            /** @var StockingProcessEntity $stockingProcess */
            $stockingProcess = $this->entityManager->getByPrimaryKey(
                StockingProcessDefinition::class,
                $stockingProcessId,
                $context,
                [
                    'state',
                ],
            );

            $this->tryStateTransition(
                $stockingProcess,
                StockingProcessStateMachine::TRANSITION_CONTINUE,
                $context,
            );
            $this->stockingProcessLineItemCalculator->recalculateStockingProcessLineItems($stockingProcessId, $context);
            $this->entityManager->update(
                StockingProcessDefinition::class,
                [
                    [
                        'id' => $stockingProcessId,
                        'userId' => ContextExtension::getUserId($context),
                        'device' => Device::getFromContext($context)->toPayload(),
                    ],
                ],
                $context,
            );
        });
    }

    public function takeOver(string $stockingProcessId, Context $context): void
    {
        /** @var StockingProcessEntity $stockingProcess */
        $stockingProcess = $this->entityManager->getByPrimaryKey(
            StockingProcessDefinition::class,
            $stockingProcessId,
            $context,
            [
                'state',
            ],
        );

        if ($stockingProcess->getState()->getTechnicalName() !== StockingProcessStateMachine::STATE_IN_PROGRESS) {
            throw StockingProcessException::invalidStockingProcessState(
                stockingProcessId: $stockingProcessId,
                currentStateName: $stockingProcess->getState()->getTechnicalName(),
                expectedStateNames: [StockingProcessStateMachine::STATE_IN_PROGRESS],
            );
        }

        $this->entityManager->update(
            StockingProcessDefinition::class,
            [
                [
                    'id' => $stockingProcessId,
                    'userId' => ContextExtension::getUserId($context),
                    'device' => Device::getFromContext($context)->toPayload(),
                ],
            ],
            $context,
        );
    }

    public function completeStockingProcess(string $stockingProcessId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($stockingProcessId, $context): void {
            // The state transition validates the current stocking process state implicitly. When we lock the stocking
            // process and execute the state transition first, we ensure that no two ::complete() functions are
            // executed on the same stocking process in parallel. (Any other execution that waits for the lock
            // release will fail when trying to execute the state transition)
            $this->entityManager->lockPessimistically(
                StockingProcessDefinition::class,
                ['id' => $stockingProcessId],
                $context,
            );

            /** @var StockingProcessEntity $stockingProcess */
            $stockingProcess = $this->entityManager->getByPrimaryKey(
                StockingProcessDefinition::class,
                $stockingProcessId,
                $context,
                [
                    'state',
                    'device',
                    'sources.goodsReceipt.state',
                ],
            );

            $this->tryStateTransition(
                $stockingProcess,
                StockingProcessStateMachine::TRANSITION_COMPLETE,
                $context,
            );

            self::validateDeviceOwnership($stockingProcess, $context);

            foreach ($stockingProcess->getSources() as $source) {
                if (
                    $source->getGoodsReceipt() !== null
                    && $source->getGoodsReceipt()->getState()->getTechnicalName() !== GoodsReceiptStateMachine::STATE_COMPLETED
                ) {
                    $this->goodsReceiptService->complete($source->getGoodsReceiptId(), $context);
                }
            }

            $this->entityManager->update(
                StockingProcessDefinition::class,
                [
                    [
                        'id' => $stockingProcessId,
                        'userId' => null,
                        'deviceId' => null,
                    ],
                ],
                $context,
            );
        });
    }

    /**
     * Requires the `device` association to be loaded of the `stockingProcess`
     */
    private static function validateDeviceOwnership(StockingProcessEntity $stockingProcess, Context $context): void
    {
        $device = Device::tryGetFromContext($context);

        if (!$device) {
            return;
        }

        if ($stockingProcess->getDeviceId() !== null && $stockingProcess->getDeviceId() !== $device->getId()) {
            throw StockingProcessException::stockingProcessInProgressByAnotherDevice(
                device: $device,
                stockingProcessId: $stockingProcess->getId(),
                deviceNameOfStockingProcess: $stockingProcess->getDevice()?->getName(),
                deviceIdOfStockingProcess: $stockingProcess->getDeviceId(),
            );
        }
    }

    /**
     * Requires the `state` association to be loaded of the `stockingProcess`
     */
    private function tryStateTransition(
        StockingProcessEntity $stockingProcess,
        string $transitionName,
        Context $context,
    ): void {
        try {
            $this->stateTransitionService->executeStateTransition(
                new Transition(
                    StockingProcessDefinition::ENTITY_NAME,
                    $stockingProcess->getId(),
                    $transitionName,
                    'stateId',
                ),
                $context,
            );
        } catch (IllegalTransitionException $e) {
            $expectedStates = (new StockingProcessStateMachine())
                ->getStatesThatAllowTransitionWithName($transitionName);

            throw StockingProcessException::invalidStockingProcessState(
                stockingProcessId: $stockingProcess->getId(),
                currentStateName: $stockingProcess->getState()->getTechnicalName(),
                expectedStateNames: array_map(
                    fn(StateMachineState $state) => $state->getTechnicalName(),
                    $expectedStates,
                ),
            );
        }
    }
}
