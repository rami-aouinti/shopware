<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemodataGeneration\Patcher;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;

class OrderRecalculationService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly RecalculationService $recalculationService,
    ) {}

    public function recalculateAllOrders(Context $context): void
    {
        $orderIds = $this->entityManager->findAllIds(OrderDefinition::class, $context);
        foreach ($orderIds as $orderId) {
            // As we cannot recalculate live orders, create a new version of the order and recalculate it
            $newVersionId = $this->entityManager->createVersion(OrderDefinition::class, $orderId, $context);
            $newContext = $context->createWithVersionId($newVersionId);
            $this->recalculationService->recalculateOrder(
                $orderId,
                $newContext,
            );

            $repository = $this->entityManager->getRepository(OrderDefinition::class);

            // change scope to be able to update write protected fields
            $context->scope(Context::SYSTEM_SCOPE, function(Context $context) use ($repository, $newVersionId): void {
                // Merge the new version back into the original order so that effectively we made the changes on the
                // live version
                $repository->merge($newVersionId, $context);
            });
        }
    }
}
