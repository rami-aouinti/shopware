<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Supplier\Controller;

use Pickware\DalBundle\CriteriaJsonSerializer;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationListItemDefinition;
use Pickware\PickwareErpStarter\Supplier\ProductSupplierConfigurationListItemService;
use Pickware\ValidationBundle\Annotation\JsonParameter;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Response\ResponseFactoryRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
class SupplierController
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly CriteriaJsonSerializer $criteriaJsonSerializer,
        private readonly ProductSupplierConfigurationListItemService $productSupplierConfigurationListItemService,
        private readonly ResponseFactoryRegistry $responseFactoryRegistry,
    ) {}

    /**
     * In the Administration, we would like to display a list of all product supplier configurations in a Shopware grid
     * component. Even when a product does not have a supplier configuration associated with it, we still would like to
     * display a row for that product without any supplier information to communicate which products are missing a
     * supplier configuration. Editing such a row should allow configuring a supplier for the product in question.
     * However, if a product has no supplier configurations, we cannot display them in a Shopware grid and creating a
     * custom grid for that behavior would be cumbersome due to pagination, sorting, in-line editing of entries etc.
     *
     * To allow displaying "non-existent" product supplier configurations in a Shopware gird, we use this custom
     * endpoint to create read-only "product supplier configuration list items". These entities are not stored in the
     * database but are created on-demand from existing products and product supplier configurations using a product
     * criteria. If a product has no supplier configuration, the product supplier configuration list item entity will
     * only provide product relevant information to be displayed in a Shopware grid.
     *
     * This way, we can easily construct a regular DAL product criteria in the Administration with correct pagination,
     * sorting, in-line editing, etc. and are only concerned with constructing the correct list items from the product
     * criteria.
     */
    #[Route(
        path: '/api/_action/pickware-erp/get-product-supplier-configuration-list-items',
        methods: ['POST'],
    )]
    public function getProductSupplierConfigurationListItems(
        #[JsonParameter] array $criteria,
        Request $request,
        Context $context,
    ): Response {
        $productCriteria = $this->criteriaJsonSerializer->deserializeFromArray($criteria, ProductDefinition::class);
        $generationResult = $this->productSupplierConfigurationListItemService->generateProductSupplierConfigurationListItems($productCriteria, $context);
        $productSupplierConfigurationListItemCollection = $this->productSupplierConfigurationListItemService
            ->createProductSupplierConfigurationListItemCollection(
                $productCriteria,
                $generationResult->getProductSupplierConfigurationListItemReferenceCollection(),
                $context,
            );

        return $this->responseFactoryRegistry
            ->getType($request)
            ->createListingResponse(
                $productCriteria,
                new EntitySearchResult(
                    ProductSupplierConfigurationListItemDefinition::class,
                    $generationResult->getTotal(),
                    $productSupplierConfigurationListItemCollection,
                    null,
                    $productCriteria,
                    $context,
                ),
                $this->entityManager->getEntityDefinition(ProductSupplierConfigurationListItemDefinition::class),
                $request,
                $context,
            );
    }
}
