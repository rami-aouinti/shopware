<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\Controller;

use DateTimeImmutable;
use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\ResponseFactory;
use function Pickware\PhpStandardLibrary\Optional\doIf;
use Pickware\PickwareErpStarter\PriceCalculation\OrderRecalculationService;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationDefinition;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationEntity;
use Pickware\PickwareErpStarter\SupplierOrder\Document\SupplierOrderDocumentContentGenerator;
use Pickware\PickwareErpStarter\SupplierOrder\Document\SupplierOrderDocumentGenerator;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderLineItemDefinition;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderCreationService;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderException;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderLineItemCreationService;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderLineItemPayloadCreationInput;
use Pickware\PickwareErpStarter\SupplierOrder\SupplierOrderService;
use Pickware\ShopwareExtensionsBundle\GeneratedDocument\GeneratedDocumentExtension as DocumentBundleResponseFactory;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Pickware\ValidationBundle\Annotation\JsonParameterAsUuid;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\DateTime as DateTimeConstraint;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class SupplierOrderController
{
    public function __construct(
        private readonly SupplierOrderCreationService $supplierOrderCreationService,
        private readonly SupplierOrderLineItemCreationService $supplierOrderLineItemCreationService,
        private readonly EntityManager $entityManager,
        private readonly OrderRecalculationService $orderRecalculationService,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
        private readonly SupplierOrderDocumentContentGenerator $supplierOrderDocumentContentGenerator,
        private readonly SupplierOrderDocumentGenerator $supplierOrderDocumentGenerator,
        private readonly SupplierOrderService $supplierOrderService,
    ) {}

    #[Route(
        path: '/api/_action/pickware-erp/supplier-order/create-supplier-orders-from-purchase-list-items',
        methods: ['POST'],
    )]
    public function createSupplierOrdersFromPurchaseListItems(Request $request, Context $context): Response
    {
        $purchaseListItemIds = $request->get('purchaseListItemIds', []);
        if (count($purchaseListItemIds) === 0) {
            return ResponseFactory::createParameterMissingResponse('purchaseListItemIds');
        }

        $supplierOrderIds = $this->supplierOrderCreationService->createSupplierOrdersFromPurchaseListItems(
            $purchaseListItemIds,
            $context,
        );

        return new JsonResponse(['supplierOrderIds' => $supplierOrderIds]);
    }

    #[Route(
        path: '/api/_action/pickware-erp/supplier-order/create-supplier-orders-from-purchase-list-item-criteria',
        methods: ['POST'],
    )]
    public function createSupplierOrdersFromPurchaseListItemCriteria(Request $request, Context $context): Response
    {
        $serializedCriteria = $request->get('criteria');
        if ($serializedCriteria === null || !is_array($serializedCriteria)) {
            return ResponseFactory::createParameterMissingResponse('criteria');
        }

        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $serializedCriteria,
            PurchaseListItemDefinition::class,
        );

        $supplierOrderIds = $this->supplierOrderCreationService->createSupplierOrdersFromPurchaseListItemCriteria(
            $criteria,
            $context,
        );

        return new JsonResponse(['supplierOrderIds' => $supplierOrderIds]);
    }

    #[Route(
        path: '/api/_action/pickware-erp/supplier-order/create',
        methods: ['POST'],
    )]
    public function createSupplierOrder(
        #[JsonParameterAsUuid] string $supplierId,
        Context $context,
    ): Response {
        $supplierOrderId = $this->supplierOrderCreationService->createSupplierOrder($supplierId, $context);

        return new JsonResponse(['supplierOrderId' => $supplierOrderId]);
    }

    #[Route(
        path: '/api/_action/pickware-erp/supplier-order/add-line-item-to-supplier-order',
        methods: ['POST'],
    )]
    public function addLineItemToSupplierOrder(
        #[JsonParameterAsUuid] string $supplierOrderId,
        #[JsonParameterAsUuid] array $lineItemPayload,
        Context $context,
    ): Response {
        if (!isset($lineItemPayload['productId']) || !Uuid::isValid($lineItemPayload['productId'])) {
            return ResponseFactory::createParameterMissingResponse('productId');
        }

        $productId = $lineItemPayload['productId'];

        /** @var ProductSupplierConfigurationEntity $productSupplierConfiguration */
        $productSupplierConfiguration = $this->entityManager->getOneBy(
            ProductSupplierConfigurationDefinition::class,
            [
                'productId' => $productId,
                'supplier.orders.id' => $supplierOrderId,
            ],
            $context,
        );

        $payload = $this->supplierOrderLineItemCreationService->createSupplierOrderLineItemPayloads(
            [
                new SupplierOrderLineItemPayloadCreationInput(
                    $productSupplierConfiguration->getId(),
                    max(1, $productSupplierConfiguration->getMinPurchase()),
                ),
            ],
            $context,
        )->first()->getPayload();

        $this->entityManager->create(
            SupplierOrderLineItemDefinition::class,
            [
                array_merge(
                    $payload,
                    $lineItemPayload,
                    ['supplierOrderId' => $supplierOrderId],
                ),
            ],
            $context,
        );

        $this->orderRecalculationService->recalculateSupplierOrders([$supplierOrderId], $context);

        return new Response('', Response::HTTP_OK);
    }

    #[Route(
        path: '/api/_action/pickware-erp/supplier-order/{supplierOrderId}/document',
        requirements: ['supplierOrderId' => '[a-fA-F0-9]{32}'],
        methods: ['GET'],
    )]
    public function getDocument(Request $request, string $supplierOrderId, Context $context): Response
    {
        $languageId = $request->query->get('languageId');
        if (!$languageId) {
            return ResponseFactory::createParameterMissingResponse('languageId');
        }
        $templateVariables = $this->supplierOrderDocumentContentGenerator->generateFromSupplierOrder(
            $supplierOrderId,
            $languageId,
            $context,
        );
        try {
            $renderedDocument = $this->supplierOrderDocumentGenerator->generate($templateVariables, $languageId, $context);
        } catch (SupplierOrderException $exception) {
            return $exception->serializeToJsonApiError()->toJsonApiErrorResponse(Response::HTTP_BAD_REQUEST);
        }

        return DocumentBundleResponseFactory::createPdfResponse(
            $renderedDocument->getContent(),
            $renderedDocument->getName(),
            $renderedDocument->getContentType(),
        );
    }

    #[Route(path: '/api/_action/pickware-erp/recalculate-supplier-orders', methods: ['POST'])]
    public function recalculate(Request $request, Context $context): Response
    {
        $supplierOrderIds = $request->get('supplierOrderIds', []);
        if (!$supplierOrderIds || count($supplierOrderIds) === 0) {
            return ResponseFactory::createParameterMissingResponse('supplierOrderIds');
        }

        $this->orderRecalculationService->recalculateSupplierOrders($supplierOrderIds, $context);

        return new Response('', Response::HTTP_OK);
    }

    #[Route(path: '/api/_action/pickware-erp/update-expected-delivery-date-of-supplier-order-and-line-items', methods: ['POST'])]
    public function updateExpectedDeliveryDateOfSupplierOrderAndLineItems(
        #[JsonParameterAsUuid] string $supplierOrderId,
        #[JsonParameter(validations: [new DateTimeConstraint(format: '!Y-m-d\\TH:i:s')])] ?string $expectedDeliveryDate,
        Context $context,
    ): Response {
        $this->supplierOrderService->updateExpectedDeliveryDateOfSupplierOrderAndLineItems(
            $supplierOrderId,
            doIf($expectedDeliveryDate, fn(string $d) => new DateTimeImmutable($d)),
            $context,
        );

        return new Response('', Response::HTTP_OK);
    }

    #[Route(path: '/api/_action/pickware-erp/update-actual-delivery-date-of-supplier-order-and-line-items', methods: ['POST'])]
    public function updateActualDeliveryDateOfSupplierOrderAndLineItems(
        #[JsonParameterAsUuid] string $supplierOrderId,
        #[JsonParameter(validations: [new DateTimeConstraint(format: '!Y-m-d\\TH:i:s')])] ?string $actualDeliveryDate,
        Context $context,
    ): Response {
        $this->supplierOrderService->updateActualDeliveryDateOfSupplierOrderAndLineItems(
            $supplierOrderId,
            doIf($actualDeliveryDate, fn(string $d) => new DateTimeImmutable($d)),
            $context,
        );

        return new Response('', Response::HTTP_OK);
    }
}
