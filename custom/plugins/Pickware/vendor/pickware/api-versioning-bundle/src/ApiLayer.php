<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiVersioningBundle;

use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface ApiLayer
{
    /**
     * Returns the API version for which compatibility is achieved by applying this API layer.
     */
    public function getVersion(): ApiVersion;

    public function transformRequest(Request $request, Context $context): void;

    public function transformResponse(Request $request, Response $response, Context $context): void;
}
