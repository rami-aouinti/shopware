<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorResponse;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorsSerializable;

class GoodsReceiptException extends Exception implements JsonApiErrorsSerializable
{
    private JsonApiErrors $jsonApiErrors;

    public function __construct(JsonApiErrors|JsonApiError $param)
    {
        $this->jsonApiErrors = $param instanceof JsonApiErrors ? $param : new JsonApiErrors([$param]);

        parent::__construct($this->jsonApiErrors->getThrowableMessage());
    }

    public function serializeToJsonApiErrors(): JsonApiErrors
    {
        return $this->jsonApiErrors;
    }

    public function toJsonApiErrorResponse(?int $status = null): JsonApiErrorResponse
    {
        return $this->jsonApiErrors->toJsonApiErrorResponse($status);
    }
}
