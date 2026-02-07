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

use LogicException;
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PhpStandardLibrary\Json\Json;
use function Pickware\PhpStandardLibrary\Optional\doIf;
use Pickware\ValidationBundle\JsonValidator;
use Pickware\ValidationBundle\JsonValidatorException;
use stdClass;

class StockMovementParser
{
    public const JSON_SCHEMA_FILE = __DIR__ . '/../Resources/json-schema/stock-movements.schema.json';

    public function __construct(private readonly JsonValidator $jsonValidator) {}

    /**
     * @return StockMovement[]
     * @throws JsonValidatorException
     */
    public function parseStockMovementsFromJson(string $json): array
    {
        $this->jsonValidator->validateJsonAgainstSchema($json, self::JSON_SCHEMA_FILE);
        $stockMovementsAsObjects = Json::decodeToObject($json);

        $stockMovements = [];
        foreach ($stockMovementsAsObjects as $stockMovementAsObject) {
            $stockMovements[] = StockMovement::create([
                'id' => $stockMovementAsObject->id,
                'productId' => $stockMovementAsObject->productId,
                'quantity' => $stockMovementAsObject->quantity,
                'source' => $this->parseStockLocation($stockMovementAsObject->source),
                'destination' => $this->parseStockLocation($stockMovementAsObject->destination),
                'comment' => $stockMovementAsObject->comment ?? null,
                'stockMovementProcessId' => $stockMovementAsObject->stockMovementProcessId ?? null,
                'batches' => doIf($stockMovementAsObject->batches, fn($batches) => new CountingMap((array) $batches)),
            ]);
        }

        return $stockMovements;
    }

    private function parseStockLocation(stdClass|string $stockLocation): StockLocationReference
    {
        if (is_string($stockLocation)) {
            return StockLocationReference::specialStockLocation($stockLocation);
        }
        if (isset($stockLocation->warehouse)) {
            return StockLocationReference::warehouse($stockLocation->warehouse->id);
        }
        if (isset($stockLocation->binLocation->id)) {
            return StockLocationReference::binLocation($stockLocation->binLocation->id);
        }
        if (isset($stockLocation->order)) {
            return StockLocationReference::order($stockLocation->order->id);
        }
        if (isset($stockLocation->returnOrder)) {
            return StockLocationReference::returnOrder($stockLocation->returnOrder->id);
        }
        if (isset($stockLocation->stockContainer)) {
            return StockLocationReference::stockContainer($stockLocation->stockContainer->id);
        }
        if (isset($stockLocation->goodsReceipt)) {
            return StockLocationReference::goodsReceipt($stockLocation->goodsReceipt->id);
        }

        throw new LogicException(
            'Could not decode a stock location. This code path should never be reachable because invalid JSON passed ' .
            'to the API should be caught by the JSON Schema validation. If you get this error, check the JSON Schema ' .
            'why it did not find the invalid stock location or check your logic why it could not be decoded.',
        );
    }
}
