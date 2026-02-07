<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\ReturnOrder\Controller;

use Pickware\ApiVersioningBundle\Attributes\ApiLayer;
use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityResponseService;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\ResponseFactory;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderRefundDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderRefundEntity;
use Pickware\PickwareErpStarter\ReturnOrder\PosLegacyReturnOrderService;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderRefundStateMachine;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderService;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderStateMachine;
use Pickware\PickwarePos\ApiVersion\ApiVersion20230721\ProductVariantApiLayer as ApiVersion20230721ProductVariantApiLayer;
use Pickware\PickwarePos\ReturnOrder\ApiVersioning\ApiVersion20240319\PlaceReturnOrderApiLayer as ApiVersion20240319PlaceReturnOrderApiLayer;
use Pickware\PickwareWms\ReturnOrder\ReturnOrderError;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\StateTransitionService;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class ReturnOrderController
{
    public function __construct(
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
        private readonly EntityManager $entityManager,
        private readonly StateTransitionService $stateTransitionService,
        private readonly ?PosLegacyReturnOrderService $posLegacyReturnOrderService,
        private readonly ReturnOrderService $returnOrderService,
        private readonly EntityResponseService $entityResponseService,
        private readonly InitialStateIdLoader $initialStateIdLoader,
    ) {}

    #[ApiLayer(ids: [
        ApiVersion20230721ProductVariantApiLayer::class,
        ApiVersion20240319PlaceReturnOrderApiLayer::class,
    ])]
    #[Route('/api/_action/pickware-pos/place-return-order', methods: ['POST'])]
    public function placeReturnOrder(Context $context, Request $request): Response
    {
        if ($this->posLegacyReturnOrderService === null) {
            return ReturnOrderError::incompatiblePickwareErpStarterInstalled()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        $returnOrderPayload = $request->get('returnOrder');
        if (!$returnOrderPayload) {
            return ResponseFactory::createParameterMissingResponse('returnOrder');
        }
        if (!isset($returnOrderPayload['id'])) {
            return ResponseFactory::createIdMissingForIdempotentCreationResponse(ReturnOrderDefinition::ENTITY_NAME);
        }
        if (!isset($returnOrderPayload['orderId'])) {
            return ResponseFactory::createParameterMissingResponse('orderId');
        }
        if (isset($returnOrderPayload['order'])) {
            return (new JsonApiError([
                'status' => (string) Response::HTTP_BAD_REQUEST,
                'title' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                'detail' => 'With this controller, the order cannot be created together with the return order. Please ' .
                    'create the order beforehand and only pass its ID with the property "orderId".',
            ]))->toJsonApiErrorResponse();
        }
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return (new JsonApiError([
                'status' => (string) Response::HTTP_BAD_REQUEST,
                'title' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                'detail' => 'Creating a return order is only allowed in live version context.',
            ]))->toJsonApiErrorResponse();
        }

        $returnOrderId = $returnOrderPayload['id'];
        $returnOrderAssociations = $request->get('returnOrderAssociations', []);
        $returnOrderSearchCriteria = $this->criteriaJsonSerializer->deserializeFromArray(
            [
                'ids' => $returnOrderId,
                'associations' => $returnOrderAssociations,
            ],
            ReturnOrderDefinition::class,
        );

        // Enforce that the logged-in user is saved as creator for the return order.
        unset($returnOrderPayload['user']);
        unset($returnOrderPayload['userId']);

        if (isset($returnOrderPayload['refund']) && is_array($returnOrderPayload['refund'])) {
            // As for the order state we want to display a state history from "open" to "completed" for the return
            // order. Therefore, we create the return order with the initial state and transition it to "completed"
            // later.
            $returnOrderPayload['refund']['stateId'] = $this->initialStateIdLoader->get(
                ReturnOrderRefundStateMachine::TECHNICAL_NAME,
            );
            unset($returnOrderPayload['refund']['state']);
        }

        /** @var ReturnOrderEntity $returnOrder */
        $returnOrder = $this->entityManager->findByPrimaryKey(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
            ['state'],
        );

        // This is an idempotency check. If the return order already exists,
        // we assume this action has been executed already.
        if (!$returnOrder) {
            $this->entityManager->runInTransactionWithRetry(
                function() use ($context, $returnOrderId, $returnOrderPayload): void {
                    $this->returnOrderService->requestReturnOrders(
                        returnOrderPayloads: [$returnOrderPayload],
                        context: $context,
                    );
                    // The return order needs to be approved. Otherwise, it cannot be received afterward.
                    $this->returnOrderService->approveReturnOrders(
                        returnOrderIds: [$returnOrderId],
                        context: $context,
                    );
                    // Transition refund to state "refunded" if it was contained in the request as we are always
                    // directly refunding returns via POS. `requestReturnOrders` will always create a refund.
                    if (isset($returnOrderPayload['refund'])) {
                        /** @var ReturnOrderRefundEntity $refund */
                        $refund = $this->entityManager->getOneBy(
                            ReturnOrderRefundDefinition::class,
                            ['returnOrderId' => $returnOrderId],
                            $context,
                        );
                        $this->stateTransitionService->executeStateTransitionIfNotAlreadyInTargetState(
                            new Transition(
                                ReturnOrderRefundDefinition::ENTITY_NAME,
                                $refund->getId(),
                                ReturnOrderRefundStateMachine::TRANSITION_REFUND,
                                'stateId',
                            ),
                            $context,
                        );
                    }

                    $this->posLegacyReturnOrderService->receiveReturnOrder($returnOrderId, $context);
                },
            );
        }

        $returnOrder = $this->entityManager->findOneBy(
            ReturnOrderDefinition::class,
            $returnOrderSearchCriteria,
            $context,
        );

        return new JsonResponse([
            'returnOrder' => $returnOrder,
        ], Response::HTTP_CREATED);
    }

    #[Route(
        path: '/api/_action/pickware-pos/complete-return-order',
        name: 'api.action.pickware-pos.complete-return-order',
        methods: ['POST'],
    )]
    public function completeReturnOrder(
        #[JsonParameterAsUuid] string $returnOrderId,
        #[JsonParameter] array $itemsToRestock,
        #[JsonParameter] array $associations,
        Context $context,
    ): Response {
        $itemsToRestock = ProductQuantityLocationImmutableCollection::fromArray($itemsToRestock);

        /** @var ReturnOrderEntity $returnOrder */
        $returnOrder = $this->entityManager->getByPrimaryKey(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
            ['state'],
        );
        if ($returnOrder->getState()->getTechnicalName() !== ReturnOrderStateMachine::STATE_COMPLETED) {
            $this->posLegacyReturnOrderService->completeReturnOrder($returnOrderId, $itemsToRestock, $context);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
            associations: $associations,
        );
    }
}
