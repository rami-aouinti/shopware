<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\ShippingProcess;

use Pickware\DalBundle\EntityManager;
use Pickware\InstallationLibrary\StateMachine\StateMachineState;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareWms\Delivery\DeliveryLineItemCalculator;
use Pickware\PickwareWms\Delivery\DeliveryStateTransitionService;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Pickware\PickwareWms\Device\Device;
use Pickware\PickwareWms\PickingProcess\DeliveryStateMachine;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessEntity;
use Pickware\PickwareWms\PickingProcess\PickingProcessService;
use Pickware\PickwareWms\PickingProcess\PickingProcessStateMachine;
use Pickware\PickwareWms\ShippingProcess\Model\ShippingProcessDefinition;
use Pickware\PickwareWms\ShippingProcess\Model\ShippingProcessEntity;
use Pickware\PickwareWms\ShippingProcess\Model\ShippingProcessNumberRange;
use Pickware\PickwareWms\ShippingProcess\Model\ShippingProcessStateMachine;
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventType;
use Pickware\PickwareWms\Statistic\Service\DeliveryLifecycleEventService;
use Pickware\PickwareWms\StockContainerClearing\StockContainerClearingService;
use Pickware\PickwareWms\StockingProcess\StockingProcessService;
use Pickware\ShopwareExtensionsBundle\Context\ContextExtension;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\StateTransitionService;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\StateMachine\Transition;

class ShippingProcessService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly InitialStateIdLoader $initialStateIdLoader,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly StateTransitionService $stateTransitionService,
        private readonly StockingProcessService $stockingProcessService,
        private readonly PickingProcessService $pickingProcessService,
        private readonly StockContainerClearingService $restockingService,
        private readonly DeliveryStateTransitionService $deliveryStateTransitionService,
        private readonly DeliveryLifecycleEventService $deliveryLifecycleEventService,
        private readonly DeliveryLineItemCalculator $deliveryLineItemCalculator,
    ) {}

    public function createShippingProcess(array $shippingProcessPayload, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(function() use ($shippingProcessPayload, $context): void {
            $pickingProcessIds = array_map(
                fn(array $payload) => $payload['id'],
                $shippingProcessPayload['pickingProcesses'],
            );
            $this->entityManager->lockPessimistically(
                PickingProcessDefinition::class,
                ['id' => $pickingProcessIds],
                $context,
            );
            $this->validatePickingProcessesForShippingProcessCreation($pickingProcessIds, $context);

            $shippingProcessPayload['stateId'] = $this->initialStateIdLoader->get(
                ShippingProcessStateMachine::TECHNICAL_NAME,
            );
            $shippingProcessPayload['number'] = $this->numberRangeValueGenerator->getValue(
                ShippingProcessNumberRange::TECHNICAL_NAME,
                $context,
                null,
            );

            $this->entityManager->create(
                ShippingProcessDefinition::class,
                [$shippingProcessPayload],
                $context,
            );
        });
    }

    public function startOrContinue(string $shippingProcessId, Context $context): void
    {
        $this->updateShippingProcessInTransaction(
            $shippingProcessId,
            $context,
            function(ShippingProcessEntity $shippingProcess, Context $context): void {
                $this->tryShippingProcessStateTransition(
                    $shippingProcess,
                    ShippingProcessStateMachine::TRANSITION_CONTINUE,
                    $context,
                );

                $this->deliveryLineItemCalculator->recalculateDeliveryLineItems(
                    deliveryIds: ImmutableCollection::create($shippingProcess->getPickingProcesses())
                        ->flatMap(
                            fn(PickingProcessEntity $pickingProcess) => $pickingProcess->getDeliveries()->getElements(),
                        )
                        ->map(fn(DeliveryEntity $delivery) => $delivery->getId())
                        ->asArray(),
                    context: $context,
                );

                $this->entityManager->update(
                    ShippingProcessDefinition::class,
                    [
                        [
                            'id' => $shippingProcess->getId(),
                            'userId' => ContextExtension::getUserId($context),
                            'device' => Device::getFromContext($context)->toPayload(),
                        ],
                    ],
                    $context,
                );
            },
            associations: [
                'state',
                'device',
                'pickingProcesses.deliveries',
            ],
        );
    }

    public function takeOver(string $shippingProcessId, Context $context): void
    {
        $this->updateShippingProcessInTransaction(
            $shippingProcessId,
            $context,
            function(ShippingProcessEntity $shippingProcess, Context $context): void {
                $currentShippingProcessState = $shippingProcess->getState()->getTechnicalName();
                if ($currentShippingProcessState !== ShippingProcessStateMachine::STATE_IN_PROGRESS) {
                    throw ShippingProcessException::invalidShippingProcessStateForAction(
                        $shippingProcess->getId(),
                        $currentShippingProcessState,
                        [ShippingProcessStateMachine::STATE_IN_PROGRESS],
                    );
                }

                $this->entityManager->update(
                    ShippingProcessDefinition::class,
                    [
                        [
                            'id' => $shippingProcess->getId(),
                            'userId' => ContextExtension::getUserId($context),
                            'device' => Device::getFromContext($context)->toPayload(),
                        ],
                    ],
                    $context,
                );
            },
        );
    }

    public function defer(string $shippingProcessId, Context $context): void
    {
        $this->updateShippingProcessInTransaction(
            $shippingProcessId,
            $context,
            function(ShippingProcessEntity $shippingProcess, Context $context): void {
                $this->tryShippingProcessStateTransition(
                    $shippingProcess,
                    ShippingProcessStateMachine::TRANSITION_DEFER,
                    $context,
                );

                $this->entityManager->update(
                    ShippingProcessDefinition::class,
                    [
                        [
                            'id' => $shippingProcess->getId(),
                            'userId' => null,
                            'deviceId' => null,
                        ],
                    ],
                    $context,
                );
            },
        );
    }

    public function complete(string $shippingProcessId, Context $context): void
    {
        $this->updateShippingProcessInTransaction(
            $shippingProcessId,
            $context,
            function(ShippingProcessEntity $shippingProcess, Context $context): void {
                $this->tryShippingProcessStateTransition(
                    $shippingProcess,
                    ShippingProcessStateMachine::TRANSITION_COMPLETE,
                    $context,
                );

                $this->ensureShippingProcessIsAssignedToCurrentDevice($shippingProcess, $context);

                $nonEmptyPreCollectingStockContainerIds = ImmutableCollection::create($shippingProcess->getPickingProcesses())
                    ->compactMap(
                        function(PickingProcessEntity $pickingProcess) {
                            if ($pickingProcess->getPreCollectingStockContainer()->getStocks()->count() > 0) {
                                return $pickingProcess->getPreCollectingStockContainer()->getId();
                            }

                            return null;
                        },
                    )
                    ->asArray();
                if (!empty($nonEmptyPreCollectingStockContainerIds)) {
                    $this->restockingService->putStockInStockContainersToUnknownLocationInWarehouse(
                        $nonEmptyPreCollectingStockContainerIds,
                        $shippingProcess->getWarehouseId(),
                        $context,
                    );
                }

                ImmutableCollection::create($shippingProcess->getPickingProcesses())
                    ->flatMap(fn(PickingProcessEntity $pickingProcess) => $pickingProcess->getDeliveries()->getElements())
                    ->filter(fn(DeliveryEntity $delivery) => $delivery->getState()->getTechnicalName() === DeliveryStateMachine::STATE_IN_PROGRESS)
                    ->forEach(function(DeliveryEntity $delivery) use ($context): void {
                        $stockContainer = $delivery->getStockContainer();
                        if (!$stockContainer || $stockContainer->getStocks()->count() === 0) {
                            // If nothing was picked into the delivery, it is cancelled, so it does not appear in the app
                            // anymore.
                            $this->deliveryStateTransitionService->tryDeliveryStateTransition(
                                $delivery->getId(),
                                DeliveryStateMachine::TRANSITION_CANCEL,
                                $context,
                            );
                            // Patches the "Cancel" event written for the given delivery by updating its user and timestamp to match the
                            // "Complete" event written for the corresponding picking process. This is necessary, since we already consider the
                            // delivery to be cancelled when all products are picked into the pre-collecting stock container (and there are not
                            // enough stocks for the delivery left). However, the delivery is only transitioned to the "Cancelled" state when all
                            // products have been picked into the delivery (since we only then know which deliveries of that picking process
                            // are actually cancelled).
                            $this->deliveryLifecycleEventService->patchDeliveryEventWithPickingProcessCompleteEvent(
                                $delivery->getPickingProcessId(),
                                $delivery->getId(),
                                DeliveryLifecycleEventType::Cancel,
                                $context,
                            );
                        } else {
                            $this->deliveryStateTransitionService->tryDeliveryStateTransition(
                                $delivery->getId(),
                                DeliveryStateMachine::TRANSITION_COMPLETE,
                                $context,
                            );
                        }
                    });

                $this->entityManager->update(
                    ShippingProcessDefinition::class,
                    [
                        [
                            'id' => $shippingProcess->getId(),
                            'userId' => null,
                            'deviceId' => null,
                        ],
                    ],
                    $context,
                );
            },
            associations: [
                'state',
                'device',
                'pickingProcesses.deliveries.state',
                'pickingProcesses.deliveries.stockContainer.stocks',
                'pickingProcesses.preCollectingStockContainer.stocks',
            ],
        );
    }

    public function cancel(string $shippingProcessId, Context $context): void
    {
        $this->updateShippingProcessInTransaction(
            $shippingProcessId,
            $context,
            function(ShippingProcessEntity $shippingProcess, Context $context): void {
                $this->tryShippingProcessStateTransition(
                    $shippingProcess,
                    ShippingProcessStateMachine::TRANSITION_CANCEL,
                    $context,
                );

                $nonEmptyStockContainerIds = [];
                foreach ($shippingProcess->getPickingProcesses() as $pickingProcess) {
                    $nonEmptyStockContainerIds = $nonEmptyStockContainerIds + $this->pickingProcessService
                        ->cancelAndCheckStockContainersForStock(
                            $pickingProcess->getId(),
                            $context,
                        );
                }

                if (!empty($nonEmptyStockContainerIds)) {
                    $this->stockingProcessService->createDeferredStockingProcess(
                        $shippingProcess->getWarehouseId(),
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
                }

                $this->entityManager->update(
                    ShippingProcessDefinition::class,
                    [
                        [
                            'id' => $shippingProcess->getId(),
                            'userId' => null,
                            'deviceId' => null,
                        ],
                    ],
                    $context,
                );
            },
            associations: [
                'state',
                'device',
                'pickingProcesses.deliveries.state',
                'pickingProcesses.deliveries.stockContainer.stocks',
                'pickingProcesses.preCollectingStockContainer.stocks',
            ],
        );
    }

    private function tryShippingProcessStateTransition(
        ShippingProcessEntity $shippingProcess,
        string $transitionName,
        Context $context,
    ): void {
        try {
            $this->stateTransitionService->executeStateTransition(
                new Transition(
                    ShippingProcessDefinition::ENTITY_NAME,
                    $shippingProcess->getId(),
                    $transitionName,
                    'stateId',
                ),
                $context,
            );
        } catch (IllegalTransitionException $e) {
            $expectedStates = (new ShippingProcessStateMachine())
                ->getStatesThatAllowTransitionWithName($transitionName);

            throw ShippingProcessException::invalidShippingProcessStateForAction(
                $shippingProcess->getId(),
                $shippingProcess->getState()->getTechnicalName(),
                array_map(fn(StateMachineState $state) => $state->getTechnicalName(), $expectedStates),
            );
        }
    }

    private function ensureShippingProcessIsAssignedToCurrentDevice(
        ShippingProcessEntity $shippingProcess,
        Context $context,
    ): void {
        if ($shippingProcess->getDeviceId() === null) {
            return;
        }

        if ($shippingProcess->getDeviceId() !== Device::getFromContext($context)->getId()) {
            throw ShippingProcessException::invalidDevice(
                $shippingProcess->getDeviceId(),
                $shippingProcess->getDevice()?->getName(),
                $shippingProcess->getId(),
            );
        }
    }

    /**
     * @param callable(ShippingProcessEntity, Context):void $callback
     */
    private function updateShippingProcessInTransaction(
        string $shippingProcessId,
        Context $context,
        callable $callback,
        array $associations = [
            'state',
            'device',
        ],
    ): void {
        $this->entityManager->runInTransactionWithRetry(
            function() use ($shippingProcessId, $context, $callback, $associations): void {
                $this->entityManager->lockPessimistically(
                    ShippingProcessDefinition::class,
                    ['id' => $shippingProcessId],
                    $context,
                );

                /** @var ShippingProcessEntity $shippingProcess */
                $shippingProcess = $this->entityManager->getByPrimaryKey(
                    ShippingProcessDefinition::class,
                    $shippingProcessId,
                    $context,
                    $associations,
                );

                $callback($shippingProcess, $context);
            },
        );
    }

    private function validatePickingProcessesForShippingProcessCreation(
        array $pickingProcessIds,
        Context $context,
    ): void {
        $pickingProcesses = ImmutableCollection::create($this->entityManager->findBy(
            PickingProcessDefinition::class,
            ['id' => $pickingProcessIds],
            $context,
            ['state'],
        ));
        $pickingProcessesWithShippingProcess = $pickingProcesses->filter(
            fn(PickingProcessEntity $pickingProcess) => $pickingProcess->getShippingProcessId() !== null,
        );
        if (count($pickingProcessesWithShippingProcess) > 0) {
            throw ShippingProcessException::pickingProcessAlreadyPartOfShippingProcess(
                $pickingProcessesWithShippingProcess
                    ->map(fn($pickingProcess) => $pickingProcess->getNumber())
                    ->asArray(),
            );
        }

        $pickingProcessesWithInvalidState = $pickingProcesses->filter(
            fn(PickingProcessEntity $pickingProcess) => $pickingProcess->getState()->getTechnicalName() !== PickingProcessStateMachine::STATE_PICKED,
        );
        if (count($pickingProcessesWithInvalidState) > 0) {
            throw ShippingProcessException::pickingProcessNotPicked(
                pickingProcessNumbers: $pickingProcessesWithInvalidState
                    ->map(fn(PickingProcessEntity $pickingProcess) => $pickingProcess->getNumber())
                    ->asArray(),
                pickingProcessStates: $pickingProcessesWithInvalidState
                    ->map(fn(PickingProcessEntity $pickingProcess) => $pickingProcess->getState()->getTechnicalName())
                    ->asArray(),
            );
        }
    }
}
