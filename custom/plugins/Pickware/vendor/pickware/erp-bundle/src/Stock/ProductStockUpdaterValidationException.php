<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorResponse;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Pickware\PickwareErpStarter\Batch\BatchStockMappingServiceValidationException;
use Throwable;

class ProductStockUpdaterValidationException extends Exception implements JsonApiErrorSerializable
{
    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError, ?Throwable $previous = null)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail(), 0, $previous);
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public function toJsonApiErrorResponse(?int $status = null): JsonApiErrorResponse
    {
        return $this->jsonApiError->toJsonApiErrorResponse($status);
    }

    public static function fromBatchStockMappingServiceValidationException(
        BatchStockMappingServiceValidationException $exception,
    ): self {
        return new self(
            $exception->serializeToJsonApiError(),
            previous: $exception,
        );
    }
}
