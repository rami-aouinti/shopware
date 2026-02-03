<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\ApiVersioning;

use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\Attributes\EntityApiLayer;
use Pickware\ApiVersioningBundle\JsonRequestModifier;
use Pickware\PickwareErpStarter\SupplierOrder\Model\SupplierOrderDefinition;
use Shopware\Core\Framework\Context;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[EntityApiLayer(entity: SupplierOrderDefinition::ENTITY_NAME, method: 'search')]
class SupplierOrderSearchApiLayer implements ApiLayer
{
    public function __construct() {}

    public function getVersion(): ApiVersion
    {
        // This API layer needs to always be active, to prevent incompatibilities between:
        // - An ERP plugin with an active multiple suppliers per product feature flag
        // - WMS App with knowledge about the existence of that feature flag
        // - A WMS without knowledge about the existence of that feature flag
        // In that case, the WMS App would not receive the correct feature flag activation status from the WMS plugin
        // and would attempt to filter using the old, incorrect `productSupplierConfiguration` key.
        return new ApiVersion('3000-01-01');
    }

    public function transformRequest(Request $request, Context $context): void
    {
        JsonRequestModifier::modifyJsonContent(
            $request,
            function(stdClass $jsonContent): void {
                $this->replaceProductSupplierConfigurationKeysInCriteriaPropertyFields($jsonContent->filter ?? []);
                $this->replaceProductSupplierConfigurationKeysInCriteriaPropertyFields($jsonContent->sort ?? []);
            },
            asObject: true,
        );
    }

    public function transformResponse(Request $request, Response $response, Context $context): void {}

    private function replaceProductSupplierConfigurationKeysInCriteriaPropertyFields(array $criteriaProperty): void
    {
        foreach ($criteriaProperty as $criterion) {
            if (isset($criterion->field)) {
                $criterion->field = str_replace(
                    'pickwareErpProductSupplierConfiguration.',
                    'pickwareErpProductSupplierConfigurations.',
                    $criterion->field,
                );
            }

            if (isset($criterion->queries) && is_array($criterion->queries)) {
                // Recursively process the nested queries
                $this->replaceProductSupplierConfigurationKeysInCriteriaPropertyFields($criterion->queries);
            }
        }
    }
}
