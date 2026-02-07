<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\BranchStore;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwarePos\BranchStore\Model\BranchStoreEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;

class BranchStoreNameProvider
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * @param string[] $orderIds
     * @return array<string, string|null> The branch store names indexed by their order id
     */
    public function getBranchStoreNamesByOrderId(array $orderIds, Context $context): array
    {
        /** @var OrderCollection $orders */
        $orders = $this->entityManager->findBy(
            OrderDefinition::class,
            ['id' => $orderIds],
            $context,
            ['pickwarePosBranchStores'],
        );

        $branchStoreNamesByOrderId = [];
        foreach ($orderIds as $orderId) {
            $order = $orders->get($orderId);
            // We know that the order can only ever be associated with a single branch store because there is a unique
            // index on the mapping table. See Pickware\PickwarePos\Order\Model\Extension\BranchStoreOrderExtension.php.
            /** @var BranchStoreEntity $branchStore */
            $branchStore = $order->getExtension('pickwarePosBranchStores')?->first();
            $branchStoreNamesByOrderId[$orderId] = $branchStore?->getName();
        }

        return $branchStoreNamesByOrderId;
    }
}
