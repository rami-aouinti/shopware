<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\ReturnOrder\Controller;

use Pickware\DalBundle\EntityManager;
use Pickware\DalBundle\EntityResponseService;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\ReturnOrder\LegacyReturnOrderService;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderDefinition;
use Pickware\PickwareErpStarter\ReturnOrder\Model\ReturnOrderEntity;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnedProduct;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderException;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderService;
use Pickware\PickwareErpStarter\ReturnOrder\ReturnOrderStateMachine;
use Pickware\PickwareWms\ReturnOrder\ReturnOrderError;
use Pickware\ShopwareExtensionsBundle\GeneratedDocument\GeneratedDocumentExtension as DocumentBundleResponseFactory;
use Pickware\ValidationBundle\Annotation\JsonValidation;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class ReturnOrderController
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly EntityResponseService $entityResponseService,
        private readonly ReturnOrderService $returnOrderService,
        private readonly LegacyReturnOrderService $legacyReturnOrderService,
    ) {}

    #[Route(path: '/api/_action/pickware-wms/request-and-receive-return-order', methods: ['POST'])]
    #[JsonValidation(schemaFilePath: 'request-and-receive-return-order.schema.json')]
    public function requestAndReceiveReturnOrder(Context $context, Request $request): Response
    {
        $returnOrderPayload = $request->get('returnOrder');

        /** @var ReturnOrderEntity $returnOrder */
        $returnOrder = $this->entityManager->findByPrimaryKey(
            ReturnOrderDefinition::class,
            $returnOrderPayload['id'],
            $context,
            ['state'],
        );

        // This is an idempotency check. If the return order is already in state 'received' we assume this action has
        // been executed already.
        if ($returnOrder && $returnOrder->getState()->getTechnicalName() === ReturnOrderStateMachine::STATE_RECEIVED) {
            return $this->entityResponseService->makeEntityDetailResponse(
                ReturnOrderDefinition::class,
                $returnOrderPayload['id'],
                $context,
            );
        }

        try {
            $this->entityManager->runInTransactionWithRetry(
                function() use ($context, $returnOrder, $returnOrderPayload): void {
                    if (!$returnOrder) {
                        $this->returnOrderService->requestReturnOrders(
                            returnOrderPayloads: [$returnOrderPayload],
                            context: $context,
                        );
                        $this->returnOrderService->addNonPhysicalLineItemsFromOrder(
                            returnOrderId: $returnOrderPayload['id'],
                            context: $context,
                        );
                        // The return order needs to be approved otherwise it cannot be received afterward.
                        $this->returnOrderService->approveReturnOrders(
                            returnOrderIds: [$returnOrderPayload['id']],
                            context: $context,
                        );
                    }

                    $returnedProducts = ImmutableCollection::create($returnOrderPayload['lineItems'])->map(
                        fn($lineItem) => new ReturnedProduct(
                            productId: $lineItem['productId'],
                            returnReason: $lineItem['reason'],
                            quantity: $lineItem['quantity'],
                        ),
                    );

                    $this->legacyReturnOrderService->receiveReturnOrder(
                        returnOrderId: $returnOrderPayload['id'],
                        returnedProducts: $returnedProducts,
                        context: $context,
                    );
                },
            );
        } catch (ReturnOrderException $exception) {
            return $exception
                ->serializeToJsonApiError()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return $this->entityResponseService->makeEntityDetailResponse(
            ReturnOrderDefinition::class,
            $returnOrderPayload['id'],
            $context,
        );
    }

    #[Route(path: '/api/_action/pickware-wms/complete-return-order-with-full-restock', methods: ['POST'])]
    #[JsonValidation(schemaFilePath: 'complete-return-order-with-full-restock.schema.json')]
    public function completeReturnOrderWithFullRestock(Context $context, Request $request): Response
    {
        $returnOrderId = $request->get('returnOrderId');
        $warehouseId = $request->get('warehouseId');

        /** @var ReturnOrderEntity $returnOrder */
        $returnOrder = $this->entityManager->getByPrimaryKey(
            ReturnOrderDefinition::class,
            $returnOrderId,
            $context,
            ['state'],
        );

        // This is an idempotency check. If the return order is already in state completed, we assume this action has
        // been executed already.
        if ($returnOrder->getState()->getTechnicalName() !== ReturnOrderStateMachine::STATE_COMPLETED) {
            $this->legacyReturnOrderService->completeReturnOrderWithFullRestock(
                returnOrderId: $returnOrderId,
                warehouseId: $warehouseId,
                context: $context,
            );
        }

        return new Response();
    }

    #[Route(path: '/api/_action/pickware-wms/return-order/{returnOrderId}/return-order-stocking-list-document', methods: 'GET')]
    public function getReturnOrderStockingListDocument(
        Request $request,
        string $returnOrderId,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => '/^[0-9a-f]{32}$/'])]
        ?string $languageId,
        Context $context,
    ): Response {
        $languageId ??= Defaults::LANGUAGE_SYSTEM;

        if (!method_exists($this->legacyReturnOrderService, 'generateReturnOrderStockingListDocument')) {
            return ReturnOrderError
                ::incompatiblePickwareErpStarterInstalled()
                ->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        $renderedDocument = $this->legacyReturnOrderService->generateReturnOrderStockingListDocument(
            returnOrderId: $returnOrderId,
            languageId: $languageId,
            context: $context,
        );

        return DocumentBundleResponseFactory::createPdfResponse(
            $renderedDocument->getContent(),
            $renderedDocument->getName(),
            $renderedDocument->getContentType(),
        );
    }
}
