<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\DeliveryNote;

use Exception;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class DeliveryNoteException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_WMS__DELIVERY_NOTE__';
    public const DELIVERY_NOTE_WOULD_BE_EMPTY = self::ERROR_CODE_NAMESPACE . 'NO_PRODUCTS_IN_DELIVERY';

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

    public static function deliveryNoteWouldBeEmpty(string $orderId, ?string $orderNumber): self
    {
        return new self(new JsonApiError([
            'code' => self::DELIVERY_NOTE_WOULD_BE_EMPTY,
            'title' => 'Delivery note would be empty',
            'detail' => sprintf(
                'The delivery note for order with number "%s" (ID: "%s") cannot be generated because it would be ' .
                'empty.',
                $orderNumber ?? '<null>',
                $orderId,
            ),
            'meta' => [
                'orderId' => $orderId,
                'orderNumber' => $orderNumber,
            ],
        ]));
    }
}
