<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Config\ApiVersioning\ApiVersion20241128;

use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareWms\ApiVersion\ApiVersion20241128;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AppConfigApiLayer implements ApiLayer
{
    public function __construct() {}

    public function getVersion(): ApiVersion
    {
        return new ApiVersion20241128();
    }

    public function transformRequest(Request $request, Context $context): void {}

    public function transformResponse(Request $request, Response $response, Context $context): void
    {
        if (!($response instanceof JsonResponse)) {
            return;
        }

        // If the content cannot be decoded, we want the client to receive the unmodified content as it might
        // contain an expected error. Throwing an error here would obfuscate the original content.
        try {
            $content = Json::decodeToArray($response->getContent());
        } catch (JsonException $exception) {
            return;
        }

        if (!isset($content['technicalFeatureFlags'])) {
            $content['technicalFeatureFlags'] = [];
        }

        $response->setContent(Json::stringify($content));
    }
}
