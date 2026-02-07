<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess\ApiVersioning\ApiVersion20250703;

use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\JsonRequestModifier;
use Pickware\PickwareWms\ApiVersion\ApiVersion20250703;
use Pickware\PickwareWms\ApiVersion\ApiVersion20250703\OrderPickwareWmsDeliveryFilterModifying;
use Shopware\Core\Framework\Context;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderSelectionCriteriaDeliveryFilterApiLayer implements ApiLayer
{
    use OrderPickwareWmsDeliveryFilterModifying;

    public function getVersion(): ApiVersion
    {
        return new ApiVersion20250703();
    }

    public function transformRequest(Request $request, Context $context): void
    {
        JsonRequestModifier::modifyJsonContent(
            $request,
            function(stdClass $jsonContent): void {
                if (property_exists($jsonContent, 'orderSelectionCriteria') && property_exists($jsonContent->orderSelectionCriteria, 'filter')) {
                    self::replaceNoPickwareWmsDeliveryFilter($jsonContent->orderSelectionCriteria->filter);
                }
            },
            asObject: true,
        );
    }

    public function transformResponse(Request $request, Response $response, Context $context): void {}
}
