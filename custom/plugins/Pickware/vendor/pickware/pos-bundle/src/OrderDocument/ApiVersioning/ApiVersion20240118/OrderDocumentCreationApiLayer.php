<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\OrderDocument\ApiVersioning\ApiVersion20240118;

use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\JsonRequestModifier;
use Pickware\PickwarePos\ApiVersion\ApiVersion20240118;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderDocumentCreationApiLayer implements ApiLayer
{
    public function getVersion(): ApiVersion
    {
        return new ApiVersion20240118();
    }

    public function transformRequest(Request $request, Context $context): void
    {
        JsonRequestModifier::modifyJsonContent(
            $request,
            fn(&$jsonContent) => self::addDocumentId($jsonContent),
        );
    }

    public function transformResponse(Request $request, Response $response, Context $context): void {}

    private static function addDocumentId(array &$jsonContent): void
    {
        if (!array_key_exists('documentId', $jsonContent)) {
            $jsonContent['documentId'] = Uuid::randomHex();
        }
    }
}
