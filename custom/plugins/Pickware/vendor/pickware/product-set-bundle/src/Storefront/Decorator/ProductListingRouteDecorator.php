<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Storefront\Decorator;

use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ProductListingRouteDecorator extends AbstractProductListingRoute
{
    public function __construct(private readonly AbstractProductListingRoute $decoratedService) {}

    public function getDecorated(): AbstractProductListingRoute
    {
        return $this->decoratedService;
    }

    public function load(string $categoryId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductListingRouteResponse
    {
        // Add the criteria to load the product set association, to determine if a product is a product set
        // when being loaded in the storefront. This method is called when a product is loaded in the
        // product detail view in the storefront. See ProductSetProductStorefrontUpdater::class
        $criteria->addAssociation('pickwareProductSetProductSet');

        return $this->decoratedService->load($categoryId, $request, $context, $criteria);
    }
}
