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
use Pickware\DalBundle\ExceptionHandling\UniqueIndexHttpException;
use Pickware\PickwareErpStarter\Stock\Model\ExceptionHandler\StockContainerUniqueIndexExceptionHandler;
use Pickware\PickwareErpStarter\Stock\Model\StockContainerDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryCollection;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventType;
use Pickware\PickwareWms\Statistic\Model\PickingProcessLifecycleEventType;
use Pickware\PickwareWms\Statistic\Service\DeliveryLifecycleEventService;
use Pickware\PickwareWms\Statistic\Service\PickingProcessLifecycleEventService;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;

class PickingProcessCreation
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly InitialStateIdLoader $initialStateIdLoader,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly PickingProcessLifecycleEventService $pickingProcessLifecycleEventService,
        private readonly DeliveryLifecycleEventService $deliveryLifecycleEventService,
    ) {}

    public function createPickingProcess(array $pickingProcessPayload, ?string $pickingProfileId, Context $context): void
    {
        $this->entityManager->runInTransactionWithRetry(
            function() use ($pickingProcessPayload, $pickingProfileId, $context): void {
                $orderIds = array_map(
                    fn(array $deliveryPayload) => $deliveryPayload['orderId'],
                    $pickingProcessPayload['deliveries'],
                );
                $this->entityManager->lockPessimistically(
                    OrderDefinition::class,
                    ['id' => $orderIds],
                    $context,
                );

                // We need to check whether any of the orders have pending deliveries because each order must have at
                // most one pending delivery. We cannot do this via the order criteria because deliveries are a to-many
                // association and hence evaluated in a sub query, which does not acquire any locks. This is done via
                // `lockPessimistically()` to ensure we read the latest state of the deliveries.
                $pendingDeliveryIds = $this->entityManager->lockPessimistically(
                    DeliveryDefinition::class,
                    [
                        'orderId' => $orderIds,
                        'state.technicalName' => DeliveryStateMachine::PENDING_STATES,
                    ],
                    $context,
                );
                if (count($pendingDeliveryIds) > 0) {
                    /** @var DeliveryCollection $pendingDeliveries */
                    $pendingDeliveries = $this->entityManager->findBy(
                        DeliveryDefinition::class,
                        ['id' => $pendingDeliveryIds],
                        $context,
                        [
                            'pickingProcess',
                            'order',
                        ],
                    );

                    throw new OrderAlreadyContainedInPickingProcessException($pendingDeliveries);
                }

                // We need to check whether stock container numbers already exist explicitly because the Shopware DAL
                // does not pass-thru a unique index exception reliably. It sometimes swallows it and throws another,
                // unrelated exception instead.
                $stockContainerNumbers = array_values(array_filter(array_map(
                    fn(array $deliveryPayload) => $deliveryPayload['stockContainer']['number'] ?? null,
                    $pickingProcessPayload['deliveries'],
                )));
                $preCollectionStockContainerNumber = $pickingProcessPayload['preCollectingStockContainer']['number'] ?? null;
                if ($preCollectionStockContainerNumber !== null) {
                    $stockContainerNumbers[] = $preCollectionStockContainerNumber;
                }
                if (count($stockContainerNumbers) !== 0) {
                    $existingStockContainers = $this->entityManager->findBy(
                        StockContainerDefinition::class,
                        ['number' => $stockContainerNumbers],
                        $context,
                    );
                    if ($existingStockContainers->count() !== 0) {
                        throw PickingProcessException::stockContainersAlreadyInUse(
                            EntityCollectionExtension::getField($existingStockContainers, 'number'),
                        );
                    }
                }

                $initialPickingProcessStateId = $this->initialStateIdLoader->get(PickingProcessStateMachine::TECHNICAL_NAME);
                $pickingProcessNumber = $this->numberRangeValueGenerator->getValue(
                    PickingProcessNumberRange::TECHNICAL_NAME,
                    $context,
                    null,
                );
                $pickingProcessPayload['id'] ??= Uuid::randomHex();
                $pickingProcessPayload['number'] = $pickingProcessNumber;
                $pickingProcessPayload['stateId'] = $initialPickingProcessStateId;
                $pickingProcessPayload['deliveries'] = array_map(
                    fn(array $deliveryPayload) => $this->completeDeliveryPayload(
                        $deliveryPayload,
                        $pickingProcessPayload['warehouseId'],
                    ),
                    $pickingProcessPayload['deliveries'],
                );

                if (isset($pickingProcessPayload['preCollectingStockContainer'])) {
                    $pickingProcessPayload['preCollectingStockContainer']['warehouseId'] = $pickingProcessPayload['warehouseId'];
                }

                $this->entityManager->create(
                    PickingProcessDefinition::class,
                    [$pickingProcessPayload],
                    $context,
                );

                $this->pickingProcessLifecycleEventService->writePickingProcessLifecycleEvent(
                    pickingProcessLifecycleEventType: PickingProcessLifecycleEventType::Create,
                    pickingProcessId: $pickingProcessPayload['id'],
                    pickingProfileId: $pickingProfileId,
                    context: $context,
                );
                $this->deliveryLifecycleEventService->writeDeliveryLifecycleEvents(
                    deliveryLifecycleEventType: DeliveryLifecycleEventType::Create,
                    deliveryIds: array_map(fn(array $deliveryPayload) => $deliveryPayload['id'], $pickingProcessPayload['deliveries']),
                    context: $context,
                );
            },
        );
    }

    public function createStockContainer(array $stockContainerPayload, Context $context): void
    {
        try {
            $this->entityManager->create(
                StockContainerDefinition::class,
                [$stockContainerPayload],
                $context,
            );
        } catch (UniqueIndexHttpException $e) {
            if ($e->getErrorCode() !== StockContainerUniqueIndexExceptionHandler::EXCEPTION_ERROR_CODE) {
                throw $e;
            }

            throw PickingProcessException::stockContainersAlreadyInUse([$stockContainerPayload['number']]);
        }
    }

    private function completeDeliveryPayload(array $deliveryPayload, string $warehouseId): array
    {
        $deliveryPayload['id'] ??= Uuid::randomHex();
        // The "InitialStateIdLoader" is cached, so no worry about performance here
        $initialDeliveryStateId = $this->initialStateIdLoader->get(DeliveryStateMachine::TECHNICAL_NAME);
        $deliveryPayload['stateId'] = $initialDeliveryStateId;
        if (isset($deliveryPayload['stockContainer'])) {
            $deliveryPayload['stockContainer']['warehouseId'] = $warehouseId;
        }

        return $deliveryPayload;
    }
}
