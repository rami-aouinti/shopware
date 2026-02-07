<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorResponse;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;
use Pickware\PickwareErpStarter\StockApi\StockLocationConfigurations;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;

class BatchStockMappingServiceValidationException extends Exception implements JsonApiErrorSerializable
{
    protected const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__BATCH_STOCK_MAPPING_SERVICE__';
    public const ERROR_CODE_OPERATION_LEADS_TO_NEGATIVE_BATCH_STOCK = self::ERROR_CODE_NAMESPACE . 'OPERATION_LEADS_TO_NEGATIVE_BATCH_STOCK';

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

    public function toJsonApiErrorResponse(?int $status = null): JsonApiErrorResponse
    {
        return $this->jsonApiError->toJsonApiErrorResponse($status);
    }

    /**
     * @param list<array{
     *     productNumber: string,
     *     batchNumber: string,
     *     stockLocationReference: StockLocationReference,
     * }> $negativeBatchStockMappings
     */
    public static function operationLeadsToNegativeBatchStock(
        array $negativeBatchStockMappings,
        StockLocationConfigurations $stockLocationConfigurations,
    ): self {
        $englishDescription = array_map(
            fn(array $batchStockMapping) => sprintf(
                '%s (product: %s, batch: %s)',
                $stockLocationConfigurations
                    ->getForStockLocation($batchStockMapping['stockLocationReference'])
                    ->getGlobalUniqueDisplayName()
                    ->getEnglish(),
                $batchStockMapping['productNumber'],
                $batchStockMapping['batchNumber'],
            ),
            $negativeBatchStockMappings,
        );
        $germanDescription = array_map(
            fn(array $batchStockMapping) => sprintf(
                '%s (Produkt: %s, Charge: %s)',
                $stockLocationConfigurations
                    ->getForStockLocation($batchStockMapping['stockLocationReference'])
                    ->getGlobalUniqueDisplayName()
                    ->getGerman(),
                $batchStockMapping['productNumber'],
                $batchStockMapping['batchNumber'],
            ),
            $negativeBatchStockMappings,
        );

        return new self(new LocalizableJsonApiError([
            'code' => self::ERROR_CODE_OPERATION_LEADS_TO_NEGATIVE_BATCH_STOCK,
            'title' => [
                'de' => 'Nicht genügend Chargenbestand',
                'en' => 'Not enough batch stock',
            ],
            'detail' => [
                'de' => sprintf(
                    'Die Aktion kann nicht durchgeführt werden, da an folgenden Orten nicht genügend Chargenbestand vorhanden ist: %s.',
                    implode(', ', $germanDescription),
                ),
                'en' => sprintf(
                    'The action cannot be performed because there is not enough batch stock at the following locations: %s.',
                    implode(', ', $englishDescription),
                ),
            ],
        ]));
    }

    /**
     * @param list<array{
     *     productNumber: string,
     *     stockLocationReference: StockLocationReference,
     * }> $duplicateBatchStockMappings
     */
    public static function binLocationContainsMultipleBatchesForSameProduct(
        array $duplicateBatchStockMappings,
        StockLocationConfigurations $stockLocationConfigurations,
    ): self {
        $englishDescription = array_map(
            fn(array $batchStockMapping) => sprintf(
                '%s (product: %s)',
                $stockLocationConfigurations
                    ->getForStockLocation($batchStockMapping['stockLocationReference'])
                    ->getGlobalUniqueDisplayName()
                    ->getEnglish(),
                $batchStockMapping['productNumber'],
            ),
            $duplicateBatchStockMappings,
        );
        $germanDescription = array_map(
            fn(array $batchStockMapping) => sprintf(
                '%s (Produkt: %s)',
                $stockLocationConfigurations
                    ->getForStockLocation($batchStockMapping['stockLocationReference'])
                    ->getGlobalUniqueDisplayName()
                    ->getGerman(),
                $batchStockMapping['productNumber'],
            ),
            $duplicateBatchStockMappings,
        );

        return new self(new LocalizableJsonApiError([
            'title' => [
                'de' => 'Lagerplatz enthält mehrere Chargen',
                'en' => 'Bin location contains multiple batches',
            ],
            'detail' => [
                'de' => sprintf(
                    'Folgende Lagerplätze würden mehrere Chargen für dasselbe Produkt enthalten: %s. ' .
                    'Lagerplätze dürfen maximal eine Charge pro Produkt enthalten.',
                    implode(', ', $germanDescription),
                ),
                'en' => sprintf(
                    'Following bin locations would contain multiple batches for the same product: %s. ' .
                    'Bin locations are only allowed to contain at most one batch per product.',
                    implode(', ', $englishDescription),
                ),
            ],
        ]));
    }
}
