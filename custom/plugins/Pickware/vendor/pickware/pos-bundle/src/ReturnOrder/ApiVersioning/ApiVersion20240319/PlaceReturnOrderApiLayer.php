<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\ReturnOrder\ApiVersioning\ApiVersion20240319;

use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderService;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderStateMachine;
use Pickware\PickwarePos\ApiVersion\ApiVersion20240319;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PlaceReturnOrderApiLayer implements ApiLayer
{
    public function __construct(
        private readonly ReturnOrderService $returnOrderService,
        private readonly EntityManager $entityManager,
    ) {}

    public function getVersion(): ApiVersion
    {
        return new ApiVersion20240319();
    }

    public function transformRequest(Request $request, Context $context): void {}

    public function transformResponse(Request $request, Response $response, Context $context): void
    {
        $returnOrderId = $request->get('returnOrder')['id'];

        /** @var ReturnOrderEntity $returnOrder */
        $returnOrder = $this->entityManager->getByPrimaryKey(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
            ['state'],
        );
        if ($returnOrder->getState()->getTechnicalName() === ReturnOrderStateMachine::STATE_COMPLETED) {
            return;
        }

        $this->entityManager->runInTransactionWithRetry(function() use ($context, $returnOrderId): void {
            $this->returnOrderService->moveStockIntoReturnOrders(
                $this->returnOrderService->getProductQuantitiesByReturnOrderId([$returnOrderId], $context),
                $context,
            );

            $this->returnOrderService->completeReturnOrders([$returnOrderId], $context);
        });

        // The response which contains the return order is not modified here, even though we modified the return order
        // in this API layer, because the app only uses the number of the return order.
    }
}
