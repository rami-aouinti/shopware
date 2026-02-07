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
use Pickware\PickwarePos\ApiVersion\ApiVersion20240118;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderDocumentUploadApiLayer implements ApiLayer
{
    public function getVersion(): ApiVersion
    {
        return new ApiVersion20240118();
    }

    public function transformRequest(Request $request, Context $context): void
    {
        if (!str_starts_with($request->headers->get('Content-Type'), 'multipart/form-data')) {
            return;
        }

        if (!$request->request->has('documentId')) {
            $request->request->set('documentId', Uuid::randomHex());
        }
    }

    public function transformResponse(Request $request, Response $response, Context $context): void {}
}
