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

use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\PickwareErpStarter\OrderShipping\PreOrderShippingValidationEvent;
use Pickware\PickwareWms\Delivery\Model\DeliveryCollection;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderShippingSubscriber implements EventSubscriberInterface
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreOrderShippingValidationEvent::EVENT_NAME => 'preOrderShippingValidation',
        ];
    }

    public function preOrderShippingValidation(PreOrderShippingValidationEvent $event): void
    {
        /** @var DeliveryCollection $pendingDeliveries */
        $pendingDeliveries = $this->entityManager->findBy(
            DeliveryDefinition::class,
            [
                'orderId' => $event->getOrderIds(),
                'state.technicalName' => DeliveryStateMachine::PENDING_STATES,
            ],
            $event->getContext(),
            ['order'],
        );

        if (count($pendingDeliveries) > 0) {
            $orderNumbers = array_values($pendingDeliveries->map(
                fn(DeliveryEntity $delivery) => $delivery->getOrder()->getOrderNumber(),
            ));
            $event->addError(new JsonApiError([
                'code' => 'PICKWARE_WMS__PICKING_PROCESS__PICKING_PROCESSES_EXIST_FOR_ORDERS_TO_BE_SHIPPED',
                'title' => 'Pending picking processes exist for orders to be shipped',
                'detail' => sprintf(
                    'Pending picking processes exist for the following orders: %s',
                    implode(', ', $orderNumbers),
                ),
                'meta' => [
                    'orderNumbers' => $orderNumbers,
                ],
            ]));
        }
    }
}
