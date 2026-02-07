<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ReturnOrder;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class ReturnOrderException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP_RETURN_ORDER_BASIC_ADMINISTRATION_BUNDLE__RETURN_ORDER__';
    public const NO_TRANSACTION_IN_ORDER = self::ERROR_CODE_NAMESPACE . 'NO_TRANSACTION_IN_ORDER';
    public const RETURN_ORDER_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'RETURN_ORDER_NOT_FOUND';
    public const ORDER_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'ORDER_NOT_FOUND';
    public const LINE_ITEM_EXCEEDS_MAXIMUM_QUANTITY = self::ERROR_CODE_NAMESPACE . 'LINE_ITEM_EXCEEDS_MAXIMUM_QUANTITY';

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

    public static function returnOrderNotFound(array $expectedReturnOrderIds, array $actualReturnOrderIds): self
    {
        return new self(new JsonApiError([
            'code' => self::RETURN_ORDER_NOT_FOUND,
            'title' => 'Return order was not found',
            'detail' => sprintf(
                'At least one of the requested return orders was not found. Expected return orders: %s. Actual return orders: %s.',
                implode(', ', $expectedReturnOrderIds),
                implode(', ', $actualReturnOrderIds),
            ),
            'meta' => [
                'expectedOrderIds' => $expectedReturnOrderIds,
                'actualOrderIds' => $actualReturnOrderIds,
            ],
        ]));
    }

    public static function orderNotFound(array $expectedOrderIds, array $actualOrderIds): self
    {
        return new self(new JsonApiError([
            'code' => self::ORDER_NOT_FOUND,
            'title' => 'Order was not found',
            'detail' => sprintf(
                'At least one of the requested orders was not found. Expected orders : %s. Actual orders: %s.',
                implode(', ', $expectedOrderIds),
                implode(', ', $actualOrderIds),
            ),
            'meta' => [
                'expectedOrderIds' => $expectedOrderIds,
                'actualOrderIds' => $actualOrderIds,
            ],
        ]));
    }

    public static function noTransactionInOrder(string $orderId): self
    {
        return new self(new JsonApiError([
            'code' => self::NO_TRANSACTION_IN_ORDER,
            'title' => 'There is no transaction in the order',
            'detail' => 'There is no transaction in the order. No refund payment method can bet set.',
            'meta' => ['orderId' => $orderId],
        ]));
    }

    public static function lineItemExceedsMaximumQuantity(string $orderLineItemId): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::LINE_ITEM_EXCEEDS_MAXIMUM_QUANTITY,
                'title' => [
                    'en' => 'Line item exceeds maximum quantity',
                    'de' => 'Position überschreitet maximale Menge',
                ],
                'detail' => [
                    'en' => 'The quantity of the line item to be returned exceeds the maximum returnable quantity.',
                    'de' => 'Die Menge der zu retournierenden Position überschreitet die maximal retournierbare Menge.',
                ],
                'meta' => [
                    'orderLineItemId' => $orderLineItemId,
                ],
            ]),
        );
    }
}
