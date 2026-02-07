<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\OrderSearch;

use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\Attributes\EntityApiLayer;
use Pickware\ApiVersioningBundle\JsonRequestModifier;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[EntityApiLayer(entity: OrderDefinition::ENTITY_NAME, method: 'search')]
class IgnoreSlowOrderSearchFiltersApiLayer implements ApiLayer
{
    public function __construct(private readonly FeatureFlagService $featureFlagService) {}

    public function getVersion(): ApiVersion
    {
        // We use a very high api version to ensure this api layer is always executed for every app version.
        return new ApiVersion('3000-01-01');
    }

    public function transformRequest(Request $request, Context $context): void
    {
        if (!$this->featureFlagService->isActive(IgnoreSlowOrderSearchFiltersFeatureFlag::NAME)) {
            return;
        }

        JsonRequestModifier::modifyJsonContent(
            $request,
            function(stdClass $jsonContent): void {
                if (($jsonContent->filter ?? null) === null) {
                    return;
                }

                self::removeLineItemProductFilters($jsonContent->filter);
            },
            asObject: true,
        );
    }

    public function transformResponse(Request $request, Response $response, Context $context): void {}

    /**
     * Removes all filters that try to execute an infix search on the product of a lineItem.
     */
    private static function removeLineItemProductFilters(array &$filters): void
    {
        foreach ($filters as $index => $filter) {
            if (($filter->queries ?? null) !== null) {
                self::removeLineItemProductFilters($filter->queries);
            }
            if (($filter->field ?? null) === null) {
                continue;
            }
            if ($filter->type === 'contains' && str_starts_with($filter->field, 'lineItems.product.')) {
                unset($filters[$index]);
            }
        }
    }
}
