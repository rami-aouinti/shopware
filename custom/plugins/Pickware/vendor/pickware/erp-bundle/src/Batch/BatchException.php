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
use Pickware\DalBundle\ExceptionHandling\UniqueIndexHttpException;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\HttpUtils\JsonApi\JsonApiErrorsSerializable;
use Throwable;

class BatchException extends Exception implements JsonApiErrorsSerializable
{
    public function __construct(private readonly JsonApiErrors $jsonApiErrors, ?Throwable $previous = null)
    {
        parent::__construct(
            message: $this->jsonApiErrors->getThrowableMessage(),
            previous: $previous,
        );
    }

    public static function duplicateBatchNumber(UniqueIndexHttpException $previousException): self
    {
        return new self($previousException->serializeToJsonApiErrors(), $previousException);
    }

    public static function stockHasMultipleBatchMappings(string $stockId): self
    {
        $jsonApiError = new LocalizableJsonApiError([
            'title' => [
                'en' => 'There are multiple batch mappings for this stock.',
                'de' => 'Für diesen Bestand sind mehrere Chargen zugeordnet.',
            ],
            'detail' => [
                'en' => 'The batch can only be changed if the stock is assigned to no batch or exactly one batch.',
                'de' => 'Die Charge kann nur geändert werden, wenn der Bestand keiner oder genau einer Charge zugeordnet ist.',
            ],
            'meta' => [
                'stockId' => $stockId,
            ],
        ]);

        return new self(new JsonApiErrors([$jsonApiError]));
    }

    public static function stockHasIncompleteBatchMapping(string $stockId): self
    {
        $jsonApiError = new LocalizableJsonApiError([
            'title' => [
                'en' => 'Batch information for this stock is incomplete.',
                'de' => 'Für diesen Bestand fehlen Chargeninformationen.',
            ],
            'detail' => [
                'en' => 'The batch can only be changed if the stock is currently assigned completely to one or no batch.',
                'de' => 'Die Charge kann nur geändert werden, wenn der Bestand aktuell vollständig einer oder keiner Charge zugeordnet ist.',
            ],
            'meta' => [
                'stockId' => $stockId,
            ],
        ]);

        return new self(new JsonApiErrors([$jsonApiError]));
    }

    public static function insufficientBatchStockForAssignment(string $batchId, string $batchNumber, int $currentQuantity): self
    {
        return new self(new JsonApiErrors([
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'There is not enough stock of the given batch available.',
                    'de' => 'Für diese Charge ist nicht genug Bestand verfügbar.',
                ],
                'detail' => [
                    'en' => sprintf(
                        'There are only %d units of batch "%s" available that can be assigned to a new batch.',
                        $currentQuantity,
                        $batchNumber,
                    ),
                    'de' => sprintf(
                        'Es sind nur %d Stück der Charge "%s" verfügbar, die neu zugewiesen werden können.',
                        $currentQuantity,
                        $batchNumber,
                    ),
                ],
                'meta' => [
                    'batchId' => $batchId,
                    'batchNumber' => $batchNumber,
                    'currentQuantity' => $currentQuantity,
                ],
            ]),
        ]));
    }

    public static function insufficientUnassignedStockForAssignment(string $productId, int $unassignedQuantity): self
    {
        return new self(new JsonApiErrors([
            new LocalizableJsonApiError([
                'title' => [
                    'en' => 'There is not enough stock available that can be assigned to a batch.',
                    'de' => 'Es ist nicht genügend Bestand verfügbar, der einer Charge zugewiesen werden kann.',
                ],
                'detail' => [
                    'en' => sprintf(
                        'There are only %d units available that can be assigned to a batch.',
                        $unassignedQuantity,
                    ),
                    'de' => sprintf(
                        'Es sind nur %d Stück verfügbar, die einer Charge zugeordnet werden können.',
                        $unassignedQuantity,
                    ),
                ],
                'meta' => [
                    'productId' => $productId,
                    'unassignedQuantity' => $unassignedQuantity,
                ],
            ]),
        ]));
    }

    public function serializeToJsonApiErrors(): JsonApiErrors
    {
        return $this->jsonApiErrors;
    }

    public static function productNotFound(string $productId): self
    {
        $jsonApiError = new LocalizableJsonApiError([
            'title' => [
                'en' => 'The product was not found.',
                'de' => 'Das Produkt wurde nicht gefunden.',
            ],
            'detail' => [
                'en' => sprintf('The product with ID "%s" was not found.', $productId),
                'de' => sprintf('Das Produkt mit der ID "%s" wurde nicht gefunden.', $productId),
            ],
            'meta' => [
                'productId' => $productId,
            ],
        ]);

        return new self(new JsonApiErrors([$jsonApiError]));
    }

    public static function trackingProfileRequiresBatchNumber(string $productId, string $productNumber): self
    {
        $jsonApiError = new LocalizableJsonApiError([
            'title' => [
                'en' => 'The tracking profile requires a batch number.',
                'de' => 'Das Trackingprofil erfordert eine Chargennummer.',
            ],
            'detail' => [
                'en' => sprintf('When creating a new batch for the product "%s", a batch number must be specified.', $productNumber),
                'de' => sprintf('Beim Erstellen einer neuen Charge für das Produkt "%s" muss eine Chargennummer angegeben werden.', $productNumber),
            ],
            'meta' => [
                'productId' => $productId,
                'productNumber' => $productNumber,
            ],
        ]);

        return new self(new JsonApiErrors([$jsonApiError]));
    }

    public static function trackingProfileRequiresBestBeforeDate(string $productId, string $productNumber): self
    {
        $jsonApiError = new LocalizableJsonApiError([
            'title' => [
                'en' => 'The tracking profile requires a best before date.',
                'de' => 'Das Trackingprofil erfordert ein Mindesthaltbarkeitsdatum.',
            ],
            'detail' => [
                'en' => sprintf('When creating a new batch for the product "%s", a best before date must be specified.', $productNumber),
                'de' => sprintf('Beim Erstellen einer neuen Charge für das Produkt "%s" muss ein Mindesthaltbarkeitsdatum angegeben werden.', $productNumber),
            ],
            'meta' => [
                'productId' => $productId,
                'productNumber' => $productNumber,
            ],
        ]);

        return new self(new JsonApiErrors([$jsonApiError]));
    }

    public static function trackingProfileRequiresBestBeforeDateAndNumber(string $getId, string $getProductNumber): self
    {
        $jsonApiError = new LocalizableJsonApiError([
            'title' => [
                'en' => 'The tracking profile requires a best before date and a batch number.',
                'de' => 'Das Trackingprofil erfordert ein Mindesthaltbarkeitsdatum und eine Chargennummer.',
            ],
            'detail' => [
                'en' => sprintf('When creating a new batch for the product "%s", a best before date and a batch number must be specified.', $getProductNumber),
                'de' => sprintf('Beim Erstellen einer neuen Charge für das Produkt "%s" muss ein Mindesthaltbarkeitsdatum und eine Chargennummer angegeben werden.', $getProductNumber),
            ],
            'meta' => [
                'productId' => $getId,
                'productNumber' => $getProductNumber,
            ],
        ]);

        return new self(new JsonApiErrors([$jsonApiError]));
    }
}
