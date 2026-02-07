<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Stocktaking;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class StocktakingException extends Exception implements JsonApiErrorSerializable
{
    private const JSON_ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__STOCKTAKING__';
    private const JSON_ERROR_STOCKTAKE_NOT_FOUND = self::JSON_ERROR_CODE_NAMESPACE . 'STOCKTAKE_NOT_FOUND';
    private const JSON_ERROR_AT_LEAST_ONE_CODE_BIN_LOCATION_ALREADY_COUNTED = self::JSON_ERROR_CODE_NAMESPACE . 'AT_LEAST_ONE_BIN_LOCATION_ALREADY_COUNTED';
    private const JSON_ERROR_CODE_STOCKTAKE_NOT_ACTIVE = self::JSON_ERROR_CODE_NAMESPACE . 'STOCKTAKE_NOT_ACTIVE';
    private const JSON_ERROR_CODE_STOCKTAKE_ALREADY_COMPLETED = self::JSON_ERROR_CODE_NAMESPACE . 'STOCKTAKE_ALREADY_COMPLETED';

    private LocalizableJsonApiError $jsonApiError;

    public function __construct(LocalizableJsonApiError $jsonApiError)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail());
    }

    /**
     * @param String[] $notFoundStocktakeIds
     */
    public static function stocktakesNotFound(array $notFoundStocktakeIds): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::JSON_ERROR_STOCKTAKE_NOT_FOUND,
                'title' => [
                    'de' => 'Ein oder mehrere Inventuren nicht gefunden',
                    'en' => 'One or more stocktakes not found',
                ],
                'detail' => [
                    'de' => sprintf(
                        'Die angeforderten Inventuren mit den IDs %s wurden nicht gefunden.',
                        implode(', ', $notFoundStocktakeIds),
                    ),
                    'en' => sprintf(
                        'The requested stocktakes with the IDs %s were not found.',
                        implode(', ', $notFoundStocktakeIds),
                    ),
                ],
                'meta' => [
                    'notFoundStocktakeIds' => $notFoundStocktakeIds,
                ],
            ]),
        );
    }

    public static function stocktakeNotActive(string $stocktakeId, string $stocktakeTitle): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::JSON_ERROR_CODE_STOCKTAKE_NOT_ACTIVE,
                'title' => [
                    'de' => 'Inventur nicht aktiv',
                    'en' => 'Stocktake not active',
                ],
                'detail' => [
                    'de' => sprintf(
                        'Die Inventur "%s" ist nicht aktiv.',
                        $stocktakeTitle,
                    ),
                    'en' => sprintf(
                        'The stocktake "%s" is not active.',
                        $stocktakeTitle,
                    ),
                ],
                'meta' => [
                    'stocktakeId' => $stocktakeId,
                    'stocktakeTitle' => $stocktakeTitle,
                ],
            ]),
        );
    }

    /**
     * @param string[] $binLocationCodes
     */
    public static function countingProcessForAtLeastOneBinLocationAlreadyExists(array $binLocationCodes): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::JSON_ERROR_AT_LEAST_ONE_CODE_BIN_LOCATION_ALREADY_COUNTED,
                'title' => [
                    'de' => 'Mindestens ein Lagerplatz bereits gezählt',
                    'en' => 'At least one bin location already counted',
                ],
                'detail' => [
                    'de' => sprintf(
                        'Mindestens einer der folgenden Lagerplätze wurde bereits gezählt: %s.',
                        implode(', ', $binLocationCodes),
                    ),
                    'en' => sprintf(
                        'At least one of the following bin locations has already been counted: %s.',
                        implode(', ', $binLocationCodes),
                    ),
                ],
                'meta' => [
                    'binLocationCodes' => $binLocationCodes,
                ],
            ]),
        );
    }

    public static function stocktakeAlreadyCompleted(string $stocktakeId, string $stocktakeTitle, string $stocktakeNumber, string $importExportId): self
    {
        return new self(
            new LocalizableJsonApiError([
                'code' => self::JSON_ERROR_CODE_STOCKTAKE_ALREADY_COMPLETED,
                'title' => [
                    'de' => 'Inventur bereits abgeschlossen',
                    'en' => 'Stocktake already completed',
                ],
                'detail' => [
                    'de' => sprintf(
                        'Die Inventur "%s" (%s) ist bereits abgeschlossen. Sie kann nicht erneut abgeschlossen werden.',
                        $stocktakeTitle,
                        $stocktakeNumber,
                    ),
                    'en' => sprintf(
                        'The stocktake "%s" (%s) is already completed. It can not be completed again.',
                        $stocktakeTitle,
                        $stocktakeNumber,
                    ),
                ],
                'meta' => [
                    'stocktakeId' => $stocktakeId,
                    'stocktakeTitle' => $stocktakeTitle,
                    'stocktakeNumber' => $stocktakeNumber,
                    'importExportId' => $importExportId,
                ],
            ]),
        );
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }
}
