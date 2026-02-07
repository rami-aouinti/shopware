<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockApi;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Throwable;

class TotalStockWriterException extends Exception implements JsonApiErrorSerializable
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__TOTAL_STOCK_WRITER__';
    private const ERROR_CODE_NOT_ENOUGH_STOCK = self::ERROR_CODE_NAMESPACE . 'NOT_ENOUGH_STOCK';
    private const ERROR_CODE_NEGATIVE_STOCK = self::ERROR_CODE_NAMESPACE . 'NEGATIVE_STOCK';

    private JsonApiError $jsonApiError;
    private string $productId;

    public function __construct(JsonApiError $apiError, string $productId, Throwable $previous)
    {
        parent::__construct($apiError->getDetail(), previous: $previous);

        $this->jsonApiError = $apiError;
        $this->productId = $productId;
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public static function notEnoughStock(string $productId, Throwable $previous): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::ERROR_CODE_NOT_ENOUGH_STOCK,
                'title' => [
                    'en' => 'Not enough stock in warehouses',
                    'de' => 'Nicht genügend Bestand in den Lagern',
                ],
                'detail' => [
                    'en' => sprintf(
                        'It is not possible to set the stock of product with ID %s to the requested value, ' .
                        'because there is not enough stock in all warehouses. The stock may be contained in picking ' .
                        'boxes or goods receipts and therefore cannot be released automatically.',
                        $productId,
                    ),
                    'de' => sprintf(
                        'Es ist nicht möglich, den Bestand des Produkts mit der ID %s auf den gewünschten Wert zu ' .
                        'setzen, weil der Bestand über alle Lager nicht ausreichend ist. Der Bestand befindet sich ' .
                        'möglicherweise in Kommissionierkisten oder Wareneingängen und kann daher nicht automatisch ' .
                        'freigegeben werden.',
                        $productId,
                    ),
                ],
                'meta' => [
                    'productId' => $productId,
                ],
            ]),
            $productId,
            $previous,
        );
    }

    public static function negativeStockNotAllowed(string $productId, Throwable $previous): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::ERROR_CODE_NEGATIVE_STOCK,
                'title' => [
                    'en' => 'Negative stock is not allowed',
                    'de' => 'Negative Bestände sind nicht erlaubt',
                ],
                'detail' => [
                    'en' => sprintf(
                        'Setting a negative stock for the product with ID %s is not allowed.',
                        $productId,
                    ),
                    'de' => sprintf(
                        'Das Setzen eines negativen Bestands für das Produkt mit der ID %s ist nicht erlaubt.',
                        $productId,
                    ),
                ],
                'meta' => [
                    'productId' => $productId,
                ],
            ]),
            $productId,
            $previous,
        );
    }
}
