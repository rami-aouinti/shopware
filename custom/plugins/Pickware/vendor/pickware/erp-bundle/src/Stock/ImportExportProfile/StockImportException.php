<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stock\ImportExportProfile;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\PickwareErpStarter\ImportExport\Exception\ImportExportException;

class StockImportException extends ImportExportException
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__STOCK_IMPORTER__';
    private const ERROR_CODE_STOCK_LOCATION_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'STOCK_LOCATION_NOT_FOUND';
    private const ERROR_CODE_PRODUCT_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'PRODUCT_NOT_FOUND';
    private const ERROR_CODE_WAREHOUSE_FOR_BIN_LOCATION_MISSING = self::ERROR_CODE_NAMESPACE . 'WAREHOUSE_FOR_BIN_LOCATION_MISSING';
    private const ERROR_CODE_UNSUPPORTED_STOCK_LOCATION = self::ERROR_CODE_NAMESPACE . 'UNSUPPORTED_STOCK_LOCATION';
    private const ERROR_CODE_NOT_ENOUGH_STOCK = self::ERROR_CODE_NAMESPACE . 'NOT_ENOUGH_STOCK';
    private const ERROR_CODE_WAREHOUSE_OR_BIN_LOCATION_FOR_DEFAULT_BIN_LOCATION_MISSING = self::ERROR_CODE_NAMESPACE . 'WAREHOUSE_OR_BIN_LOCATION_FOR_DEFAULT_BIN_LOCATION_MISSING';

    public static function createUnsupportedStockLocationError(): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_UNSUPPORTED_STOCK_LOCATION,
            'title' => 'Stock location not supported',
            'detail' => 'This stock location is not supported.',
        ]);
    }

    public static function createStockLocationNotFoundError(): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_STOCK_LOCATION_NOT_FOUND,
            'title' => 'Stock location not found',
            'detail' => 'This stock location could not be found.',
        ]);
    }

    public static function createProductNotFoundError(string $productNumber): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_PRODUCT_NOT_FOUND,
            'title' => 'Product not found',
            'detail' => sprintf('The product with the number "%s" could not be found.', $productNumber),
            'meta' => [
                'productNumber' => $productNumber,
            ],
        ]);
    }

    public static function createWarehouseForBinLocationMissing(): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_WAREHOUSE_FOR_BIN_LOCATION_MISSING,
            'title' => 'Warehouse for bin location missing',
            'detail' => 'A bin location cannot be specified without a warehouse.',
        ]);
    }

    public static function createBinLocationOrWarehouseForDefaultBinLocationMissing(): JsonApiError
    {
        return new JsonApiError([
            'code' => self::ERROR_CODE_WAREHOUSE_OR_BIN_LOCATION_FOR_DEFAULT_BIN_LOCATION_MISSING,
            'title' => 'Warehouse or bin location for default bin location missing',
            'detail' => 'A default bin location cannot be specified without a warehouse and a bin location.',
        ]);
    }

    public static function createQuantityExceedsMaximumError(): JsonApiError
    {
        return new LocalizableJsonApiError([
            'title' => [
                'en' => 'Quantity exceeds maximum',
                'de' => 'Menge übersteigt Maximum',
            ],
            'detail' => [
                'en' => 'The quantity exceeds the maximum allowed quantity of 1,000,000,000.',
                'de' => 'Die Menge übersteigt die maximal erlaubte Menge von 1.000.000.000.',
            ],
        ]);
    }
}
