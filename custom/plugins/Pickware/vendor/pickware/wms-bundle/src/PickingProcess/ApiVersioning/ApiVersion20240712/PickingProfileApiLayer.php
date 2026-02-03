<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess\ApiVersioning\ApiVersion20240712;

use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\JsonRequestModifier;
use Pickware\PickwareWms\ApiVersion\ApiVersion20240712;
use Shopware\Core\Framework\Context;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class PickingProfileApiLayer implements ApiLayer
{
    public const PICKING_PROFILE_ID = '5f365840a47e4235893d63219c2f6e3d';

    public function __construct() {}

    public function getVersion(): ApiVersion
    {
        return new ApiVersion20240712();
    }

    public function transformRequest(Request $request, Context $context): void
    {
        JsonRequestModifier::modifyJsonContent(
            $request,
            function(stdClass $jsonContent): void {
                $jsonContent->pickingProfileId = self::PICKING_PROFILE_ID;
            },
            asObject: true,
        );
    }

    public function transformResponse(Request $request, Response $response, Context $context): void {}
}
