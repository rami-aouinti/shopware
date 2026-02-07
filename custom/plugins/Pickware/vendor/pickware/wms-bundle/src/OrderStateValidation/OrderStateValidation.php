<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\OrderStateValidation;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareWms\PickingProcess\PickingProcessException;
use Pickware\PickwareWms\PickingProfile\PickingProfileService;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class OrderStateValidation
{
    public const ORDER_STATE_ALLOW_LIST_FOR_PICKING_PROCESS_START = [
        OrderStates::STATE_OPEN,
        OrderStates::STATE_IN_PROGRESS,
    ];
    public const ERROR_CODE_INVALID_ORDER_STATE = PickingProcessException::ERROR_CODE_NAMESPACE . 'INVALID_ORDER_STATE';
    public const ERROR_CODE_ORDER_DOES_NOT_PASS_FILTER = PickingProcessException::ERROR_CODE_NAMESPACE . 'ORDER_DOES_NOT_PASS_FILTER';

    public function __construct(
        private readonly PickingProfileService $pickingProfileService,
        private readonly EntityManager $entityManager,
    ) {}

    public function getStateViolationsForOrdersOfPickingProcess(
        string $pickingProcessId,
        string $pickingProfileId,
        Context $context,
    ): JsonApiErrors {
        /** @var OrderCollection $orders */
        $orders = $this->entityManager->findBy(
            OrderDefinition::class,
            ['pickwareWmsDeliveries.pickingProcessId' => $pickingProcessId],
            $context,
            [
                'stateMachineState',
                'transactions.stateMachineState',
            ],
        );

        $pickingProfileFilterCriteria = $this->pickingProfileService->createOrderFilterCriteria(
            $pickingProfileId,
            $context,
        );
        $pickingProfileFilterCriteria->addFilter(new EqualsFilter('pickwareWmsDeliveries.pickingProcessId', $pickingProcessId));
        $orderIdsMatchingPickingProfileFilter = $this->entityManager->findIdsBy(
            OrderDefinition::class,
            $pickingProfileFilterCriteria,
            $context,
        );

        $errors = array_merge(...array_values(
            $orders->map(fn(OrderEntity $order) => $this->getOrderStateViolations($order, $orderIdsMatchingPickingProfileFilter)),
        ));

        return new JsonApiErrors($errors);
    }

    /**
     * @param array<string> $orderIdsMatchingPickingProfileFilter
     * @return array<JsonApiError>
     */
    private function getOrderStateViolations(
        OrderEntity $order,
        array $orderIdsMatchingPickingProfileFilter,
    ): array {
        $errors = [];

        if (
            !in_array(
                $order->getStateMachineState()->getTechnicalName(),
                self::ORDER_STATE_ALLOW_LIST_FOR_PICKING_PROCESS_START,
                true,
            )
        ) {
            $errors[] = new LocalizableJsonApiError([
                'code' => self::ERROR_CODE_INVALID_ORDER_STATE,
                'title' => [
                    'en' => 'Invalid order state',
                    'de' => 'Ungültiger Bestellstatus',
                ],
                'detail' => [
                    'en' => sprintf(
                        'Order with ID "%s" is in invalid state %s for picking. Allowed order states: %s.',
                        $order->getId(),
                        $order->getStateMachineState()->getTechnicalName(),
                        implode(', ', self::ORDER_STATE_ALLOW_LIST_FOR_PICKING_PROCESS_START),
                    ),
                    'de' => sprintf(
                        'Bestellung mit ID "%s" hat einen ungültigen Status %s für das Picken. Erlaubte Bestellstatus: %s.',
                        $order->getId(),
                        $order->getStateMachineState()->getTechnicalName(),
                        implode(', ', self::ORDER_STATE_ALLOW_LIST_FOR_PICKING_PROCESS_START),
                    ),
                ],
                'meta' => [
                    'orderId' => $order->getId(),
                    'actualOrderState' => $order->getStateMachineState()->getTechnicalName(),
                    'allowedOrderStates' => self::ORDER_STATE_ALLOW_LIST_FOR_PICKING_PROCESS_START,
                ],
            ]);
        }

        if (!in_array($order->getId(), $orderIdsMatchingPickingProfileFilter, true)) {
            $errors[] = new LocalizableJsonApiError([
                'code' => self::ERROR_CODE_ORDER_DOES_NOT_PASS_FILTER,
                'title' => [
                    'en' => 'Order does not pass filter.',
                    'de' => 'Bestellung wird vom Filter ausgeschlossen.',
                ],
                'detail' => [
                    'en' => sprintf(
                        'Order with ID "%s" does not pass the picking profile filter.',
                        $order->getId(),
                    ),
                    'de' => sprintf(
                        'Bestellung mit ID "%s" wird vom Pickprofil-Filter ausgeschlossen.',
                        $order->getId(),
                    ),
                ],
                'meta' => ['orderId' => $order->getId()],
            ]);
        }

        return $errors;
    }
}
