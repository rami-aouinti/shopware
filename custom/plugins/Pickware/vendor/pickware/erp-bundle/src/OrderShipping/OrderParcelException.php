<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Throwable;

class OrderParcelException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__ORDER_PARCEL__';
    public const ERROR_CODE_ORDER_OVERFULFILLED = self::ERROR_CODE_NAMESPACE . 'ORDER_OVERFULFILLED';

    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError, ?Throwable $previous = null)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail(), previous: $previous);
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }
}
