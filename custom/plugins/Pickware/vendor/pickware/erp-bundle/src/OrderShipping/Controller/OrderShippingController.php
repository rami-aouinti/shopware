<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping\Controller;

use Pickware\HttpUtils\ResponseFactory;
use Pickware\PickwareErpStarter\OrderShipping\OrderShippingException;
use Pickware\PickwareErpStarter\OrderShipping\OrderShippingService;
use Pickware\PickwareErpStarter\Picking\OrderQuantitiesToShipCalculator;
use Pickware\ValidationBundle\Annotation\JsonParameterAsArrayOfUuids;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class OrderShippingController
{
    public function __construct(
        private readonly OrderShippingService $orderShippingService,
        private readonly OrderQuantitiesToShipCalculator $orderQuantitiesToShipCalculator,
    ) {}

    #[Route(
        path: '/api/_action/pickware-erp/ship-order-completely',
        name: 'api.action.pickware-erp.ship-order-completely',
        methods: ['POST'],
    )]
    public function shipOrderCompletely(Request $request, Context $context): JsonResponse
    {
        $warehouseId = $request->get('warehouseId');
        if (!$warehouseId || !Uuid::isValid($warehouseId)) {
            return ResponseFactory::createUuidParameterMissingResponse('warehouseId');
        }
        $orderId = $request->get('orderId');
        if (!$orderId || !Uuid::isValid($orderId)) {
            return ResponseFactory::createUuidParameterMissingResponse('orderId');
        }

        // Shopware supports sending (selected) documents along with status mails, which are send in reaction to state
        // transitions. In order to mimic this behavior for the "ship" transition, we need to pass relevant document ids
        // to the mailer service, which is done via a MailSendSubscriberConfig stored in the context object.
        // (See Shopware\Core\Checkout\Order\Api\OrderActionController::orderStateTransition).
        $context->addExtension(
            SendMailAction::MAIL_CONFIG_EXTENSION,
            new MailSendSubscriberConfig(
                $request->request->get('sendMail', true) === false,
                $request->request->all('documentIds'),
            ),
        );

        try {
            $shippedQuantity = $this->orderShippingService->shipOrderCompletely($orderId, $warehouseId, $context);
        } catch (OrderShippingException $e) {
            return $e->serializeToJsonApiError()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($shippedQuantity);
    }

    #[Route(
        path: '/api/_action/pickware-erp/calculate-products-to-ship-for-orders',
        name: 'api.action.pickware-erp.calculate-products-to-ship-for-orders',
        methods: ['POST'],
    )]
    public function calculateProductsToShipForOrders(
        #[JsonParameterAsArrayOfUuids] array $orderIds,
        Context $context,
    ): JsonResponse {
        $productQuantities = $this->orderQuantitiesToShipCalculator->calculateProductsToShipForOrders($orderIds, $context);

        return new JsonResponse($productQuantities);
    }
}
