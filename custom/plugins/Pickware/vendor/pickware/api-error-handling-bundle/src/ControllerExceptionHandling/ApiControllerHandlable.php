<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ApiErrorHandlingBundle\ControllerExceptionHandling;

use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

/**
 * Indicates that this exception can be handled in the Shopware API controller for Entities. For example, to convert
 * SQL constraint violations into a proper 400 response with a JSON:API error object.
 */
interface ApiControllerHandlable extends Throwable
{
    public function handleForApiController(): JsonResponse;
}
