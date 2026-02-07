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

class AvailableStockWriterException extends Exception implements JsonApiErrorSerializable
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__AVAILABLE_STOCK_WRITER__';
    private const ERROR_CODE_NOT_ENOUGH_STOCK = self::ERROR_CODE_NAMESPACE . 'NOT_ENOUGH_STOCK';

    private function __construct(
        private readonly JsonApiError $jsonApiError,
        private readonly array $productIds,
        ?Throwable $previous = null,
    ) {
        parent::__construct($jsonApiError->getDetail(), previous: $previous);
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    /**
     * @return string[]
     */
    public function getProductIds(): array
    {
        return $this->productIds;
    }

    public static function notEnoughStock(array $productIds, Throwable $previous): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::ERROR_CODE_NOT_ENOUGH_STOCK,
                'title' => [
                    'en' => 'Not enough stock in warehouses',
                    'de' => 'Nicht genügend Bestand in den Lagern',
                ],
                'detail' => [
                    'en' => 'It is not possible to set the available stock to the requested value, because there is ' .
                        'not enough stock in all available warehouses. The stock may be contained in picking boxes ' .
                        'or goods receipts and therefore cannot be released automatically.',
                    'de' => 'Es ist nicht möglich, den verfügbaren Bestand auf den gewünschten Wert zu setzen, weil ' .
                        'der Bestand über alle verfügbaren Lager nicht ausreichend ist. Der Bestand befindet sich ' .
                        'möglicherweise in Kommissionierkisten oder Wareneingängen und kann daher nicht automatisch ' .
                        'freigegeben werden.',
                ],
                'meta' => [
                    'productIds' => $productIds,
                ],
            ]),
            $productIds,
            $previous,
        );
    }
}
