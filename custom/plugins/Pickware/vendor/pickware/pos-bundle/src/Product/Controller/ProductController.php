<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Product\Controller;

use Pickware\ApiVersioningBundle\Attributes\ApiLayer;
use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\ResponseFactory;
use Pickware\PickwarePos\ApiVersion\ApiVersion20230721\ProductVariantApiLayer as ApiVersion20230721ProductVariantApiLayer;
use Pickware\PickwarePos\BranchStore\Model\BranchStoreDefinition;
use Pickware\PickwarePos\BranchStore\Model\BranchStoreEntity;
use Pickware\PickwarePos\Product\ProductSearchService;
use Pickware\PickwarePos\Product\ProductTopSellerService;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Response\ResponseFactoryRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class ProductController
{
    private ProductTopSellerService $productTopSellerService;
    private CriteriaJsonSerializer $criteriaJsonSerializer;
    private EntityManager $entityManager;
    private ProductSearchService $productSearchService;
    private ResponseFactoryRegistry $responseFactoryRegistry;

    public function __construct(
        CriteriaJsonSerializer $criteriaJsonSerializer,
        EntityManager $entityManager,
        ProductTopSellerService $productTopSellerService,
        ProductSearchService $productSearchService,
        ResponseFactoryRegistry $responseFactoryRegistry,
    ) {
        $this->criteriaJsonSerializer = $criteriaJsonSerializer;
        $this->entityManager = $entityManager;
        $this->productTopSellerService = $productTopSellerService;
        $this->productSearchService = $productSearchService;
        $this->responseFactoryRegistry = $responseFactoryRegistry;
    }

    #[ApiLayer(ids: [ApiVersion20230721ProductVariantApiLayer::class])]
    #[Route(path: '/api/_action/pickware-pos/product/get-top-seller', methods: ['POST'])]
    public function getTopSeller(Context $context, Request $request): Response
    {
        $branchStoreId = $request->get('branchStoreId');
        if (!$branchStoreId || !Uuid::isValid($branchStoreId)) {
            return ResponseFactory::createUuidParameterMissingResponse('branchStoreId');
        }
        $limit = $request->request->getInt('limit', 25);

        /** @var BranchStoreEntity $branchStore */
        $branchStore = $this->entityManager->getByPrimaryKey(BranchStoreDefinition::class, $branchStoreId, $context);
        $salesChannelId = $branchStore->getSalesChannelId();
        if ($salesChannelId === null) {
            return (new JsonApiError([
                'status' => Response::HTTP_BAD_REQUEST,
                'title' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                'detail' => sprintf('No sales channel has been found for branch store with id %s', $branchStoreId),
            ]))->toJsonApiErrorResponse();
        }

        $topSellerIds = $this->productTopSellerService->getTopSellerIds($salesChannelId, $limit);
        if (count($topSellerIds) === 0) {
            return new JsonResponse([]);
        }

        $productAssociations = $request->get('productAssociations', []);
        $productSearchCriteria = $this->criteriaJsonSerializer->deserializeFromArray(
            ['associations' => $productAssociations],
            ProductDefinition::class,
        );
        $productSearchCriteria->setIds($topSellerIds);
        $topSeller = $this->entityManager->findBy(ProductDefinition::class, $productSearchCriteria, $context);

        return new JsonResponse($topSeller);
    }

    #[ApiLayer(ids: [ApiVersion20230721ProductVariantApiLayer::class])]
    #[Route(path: '/api/_action/pickware-pos/product/search-with-variant-grouping', methods: ['POST'])]
    public function searchWithVariantGrouping(Context $context, Request $request): Response
    {
        $productSearchCriteria = $this->criteriaJsonSerializer->deserializeFromArray(
            $request->request->all(),
            ProductDefinition::class,
        );

        $mainProductVariantCounts = $this->productSearchService->findMainProductVariantCountsOfProductsMatching(
            $productSearchCriteria->getFilters(),
            $context,
        );

        // To improve the search results we do not always return the main products for matching variants, specifically
        // when we have exactly one result. For example when search for "T-Shirt Blue" we expect to see the blue T-Shirt
        // in the search results, instead of the meta product "T-Shirt". This also results in the correct variant being
        // preselected in the variant picker in the app.
        if (
            count($mainProductVariantCounts) > 1
            || (count($mainProductVariantCounts) === 1 && $mainProductVariantCounts[0]->getVariantCount() > 1)
        ) {
            $productSearchCriteria->resetFilters();
            $mainProductIds = array_map(
                fn($mainProductVariantCount) => $mainProductVariantCount->getMainProductId(),
                $mainProductVariantCounts,
            );
            $productSearchCriteria->addFilter(new EqualsAnyFilter('id', $mainProductIds));
        }

        // Use the repository instead of the entity manager search function because we need an entity search result for
        // the listing response
        $productSearchResult = $this->entityManager
            ->getRepository(ProductDefinition::class)
            ->search($productSearchCriteria, $context);

        return $this->responseFactoryRegistry
            ->getType($request)
            ->createListingResponse(
                $productSearchCriteria,
                $productSearchResult,
                $this->entityManager->getEntityDefinition(ProductDefinition::class),
                $request,
                $context,
            );
    }
}
