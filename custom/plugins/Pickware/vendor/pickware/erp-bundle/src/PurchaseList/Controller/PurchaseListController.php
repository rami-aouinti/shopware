<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\PurchaseList\Controller;

use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\PickwareErpStarter\PurchaseList\Model\PurchaseListItemDefinition;
use Pickware\PickwareErpStarter\PurchaseList\PurchaseListService;
use Pickware\PickwareErpStarter\PurchaseList\PurchaseListSupplierConfigurationAssignmentStrategy;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Pickware\ValidationBundle\Annotation\JsonParameterAsArrayOfUuids;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class PurchaseListController
{
    public function __construct(
        private readonly PurchaseListService $purchaseListService,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
    ) {}

    #[Route(path: '/api/_action/pickware-erp/purchase-list/clear', methods: ['POST'])]
    public function clear(): Response
    {
        $this->purchaseListService->clearPurchaseList();

        return new JsonResponse();
    }

    #[Route(path: '/api/_action/pickware-erp/purchase-list/has-purchase-list-items-without-supplier', methods: ['GET'])]
    public function hasPurchaseListItemsWithoutSupplier(Context $context): Response
    {
        return new JsonResponse([
            'hasPurchaseListItemsWithoutSupplier' => $this->purchaseListService->hasPurchaseListItemsWithoutSupplier($context),
        ]);
    }

    /**
     * @param array<string, mixed> $criteriaFilter
     */
    #[Route(path: '/api/_action/pickware-erp/purchase-list/get-filtered-purchase-price-total-net', methods: ['POST'])]
    public function getFilteredPurchasePriceTotalNet(
        #[JsonParameter] array $criteriaFilter,
        Context $context,
    ): Response {
        $criteria = $this->criteriaJsonSerializer->deserializeFromArray(
            ['filter' => $criteriaFilter],
            PurchaseListItemDefinition::class,
        );

        return new JsonResponse([
            'filteredPurchasePriceTotalNet' => $this->purchaseListService->getFilteredPurchasePriceTotalNet($criteria, $context),
        ]);
    }

    /**
     * @param array<string> $productIds
     */
    #[Route(path: '/api/_action/pickware-erp/purchase-list/get-supplier-configuration-for-products-with-strategy', methods: ['POST'])]
    public function getSupplierConfigurationForProductsWithStrategy(
        #[JsonParameterAsArrayOfUuids] array $productIds,
        #[JsonParameter] PurchaseListSupplierConfigurationAssignmentStrategy $strategy,
    ): Response {
        return new JsonResponse($this->purchaseListService->getSupplierConfigurationForProductsWithStrategy($productIds, $strategy));
    }
}
