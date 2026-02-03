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
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class OrderStockInitializerException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__ORDER_INITIALIZING__';
    public const ERROR_CODE_ORDER_DOES_NOT_EXIST = self::ERROR_CODE_NAMESPACE . 'ORDER_DOES_NOT_EXIST';

    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function orderDoesNotExist(string $orderId): self
    {
        $jsonApiError = new JsonApiError([
            'code' => self::ERROR_CODE_ORDER_DOES_NOT_EXIST,
            'title' => 'Order does not exist',
            'detail' => sprintf(
                'Order with ID=%s does not exist.',
                $orderId,
            ),
            'meta' => [
                'orderId' => $orderId,
            ],
        ]);

        return new self($jsonApiError);
    }
}
