<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\OrderDelivery;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class OrderDeliveryService
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getPrimaryOrderDeliveryStates(array $orderIds, Context $context): array
    {
        if (count($orderIds) === 0) {
            return [];
        }

        /** @var OrderCollection $orders */
        $orders = $this->entityManager->findBy(
            OrderDefinition::class,
            new Criteria($orderIds),
            $context,
            ['deliveries.stateMachineState'],
        );
        $primaryDeliveryStates = [];

        foreach ($orders as $order) {
            /** @var ?OrderDeliveryEntity $primaryDelivery */
            $primaryDelivery = OrderDeliveryCollectionExtension::primaryOrderDelivery($order->getDeliveries());

            if (!$primaryDelivery || !$primaryDelivery->getStateMachineState()) {
                continue;
            }

            $primaryDeliveryStates[] = [
                'orderId' => $order->getId(),
                'deliveryStateTechnicalName' => $primaryDelivery->getStateMachineState()->getTechnicalName(),
            ];
        }

        return $primaryDeliveryStates;
    }
}
