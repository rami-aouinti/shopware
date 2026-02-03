<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\EntitySnapshotGeneration;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

/**
 * @phpstan-type OrderSnapshot array{orderNumber: string}
 * @implements EntitySnapshotGenerator<OrderSnapshot>
 */
#[AsEntitySnapshotGenerator(entityClass: OrderDefinition::class)]
class OrderSnapshotGenerator implements EntitySnapshotGenerator
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function generateSnapshots(array $ids, Context $context): array
    {
        return $this->entityManager->findBy(
            OrderDefinition::class,
            ['id' => $ids],
            $context,
        )->map(fn(OrderEntity $order) => [
            'orderNumber' => $order->getOrderNumber(),
        ]);
    }
}
