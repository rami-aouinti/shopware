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
use Pickware\HttpUtils\JsonApi\JsonApiErrorResponse;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Pickware\PickwareErpStarter\Stock\ProductStockUpdaterValidationException;
use Throwable;

class StockMovementServiceValidationException extends Exception implements JsonApiErrorSerializable
{
    protected const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__STOCK_MOVEMENT_SERVICE_VALIDATION__';
    public const ERROR_CODE_INVALID_STOCK_MOVEMENT_FOR_NOT_STOCK_MANAGED_PRODUCTS = self::ERROR_CODE_NAMESPACE . 'INVALID_STOCK_MOVEMENT_FOR_NOT_STOCK_MANAGED_PRODUCTS';
    public const ERROR_CODE_INVALID_COMBINATION_OF_STOCK_LOCATIONS = self::ERROR_CODE_NAMESPACE . 'INVALID_COMBINATION_OF_STOCK_LOCATIONS';

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

    /**
     * @param array $invalidCombinations Array of array that must contain properties 'source' and 'destination'. E.g.:
     * [
     *   [
     *     'source' => 'order',
     *     'destination' => 'warehouse',
     *   ],
     * ]
     */
    public static function invalidCombinationOfSourceAndDestinationStockLocations(
        array $invalidCombinations,
    ): StockMovementServiceValidationException {
        $uniqueInvalidCombinations = array_unique($invalidCombinations, SORT_REGULAR);
        $formattedCombinations = array_map(
            fn(array $combination): string => sprintf('Source: %s, destination: %s.', $combination['source'], $combination['destination']),
            $uniqueInvalidCombinations,
        );

        return new self(new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_INVALID_COMBINATION_OF_STOCK_LOCATIONS,
            'title' => [
                'de' => 'Ungültige Kombination von Lagerorten',
                'en' => 'Invalid combination of stock locations',
            ],
            'detail' => [
                'de' => sprintf(
                    'Die Bestandsbewegung konnte nicht ausgeführt werden, da folgende Kombinationen von Quell- zu ' .
                    'Ziellagerort ungültig sind: %s',
                    implode(' ', $formattedCombinations),
                ),
                'en' => sprintf(
                    'The attempted operation was aborted because at least one invalid combination of source stock ' .
                    'location and destination stock location was used: %s',
                    implode(' ', $formattedCombinations),
                ),
            ],
            'meta' => [
                'invalidCombinations' => $uniqueInvalidCombinations,
            ],
        ]));
    }

    /**
     * @param string[] $productIds
     */
    public static function operationMovesStockToBinLocationsForNotStockManagedProducts(array $productIds): self
    {
        return new self(new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_INVALID_STOCK_MOVEMENT_FOR_NOT_STOCK_MANAGED_PRODUCTS,
            'title' => [
                'de' => 'Produkt ist nicht bestandsgeführt',
                'en' => 'Operation moves stock for not stock managed products to bin locations',
            ],
            'detail' => [
                'de' => sprintf(
                    'Die Bestandsbewegung konnte nicht ausgeführt werden, da folgende Produkte nicht bestandsgeführt ' .
                    'sind: %s ',
                    implode(', ', $productIds),
                ),
                'en' => sprintf(
                    'The attempted operation was aborted because it would move stock for not stock managed products ' .
                    'into bin locations. Product ids: %s.',
                    implode(', ', $productIds),
                ),
            ],
            'meta' => [
                'productIds' => $productIds,
            ],
        ]));
    }

    public static function fromProductStockUpdaterValidationException(ProductStockUpdaterValidationException $exception): self
    {
        return new self(
            $exception->serializeToJsonApiError(),
            previous: $exception,
        );
    }
}
