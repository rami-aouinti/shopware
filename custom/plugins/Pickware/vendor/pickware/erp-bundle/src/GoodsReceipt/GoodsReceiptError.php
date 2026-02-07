<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\GoodsReceipt;

use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;

class GoodsReceiptError
{
    public const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP_STARTER__GOODS_RECEIPT__';
    public const CODE_INVALID_GOODS_RECEIPT_STATE_FOR_ACTION = self::ERROR_CODE_NAMESPACE . 'INVALID_GOODS_RECEIPT_STATE_FOR_ACTION';
    public const CODE_ROUNDING_CONFIGURATION_MISSING = self::ERROR_CODE_NAMESPACE . 'ROUNDING_CONFIGURATION_MISSING';
    public const CODE_GOODS_RECEIPT_STILL_CONTAINS_STOCK = self::ERROR_CODE_NAMESPACE . 'GOODS_RECEIPT_STILL_CONTAINS_STOCK';
    public const CODE_GOODS_RECEIPT_NOT_FOUND = self::ERROR_CODE_NAMESPACE . 'GOODS_RECEIPT_NOT_FOUND';
    public const CODE_MISSING_WAREHOUSE = self::ERROR_CODE_NAMESPACE . 'MISSING_WAREHOUSE';
    public const CODE_GOODS_RECEIPT_PART_OF_STOCK_VALUATION = self::ERROR_CODE_NAMESPACE . 'GOODS_RECEIPT_PART_OF_STOCK_VALUATION';

    /**
     * @param string[] $expectedStateNames
     */
    public static function invalidGoodsReceiptStateForAction(
        string $goodsReceiptId,
        string $actualStateName,
        array $expectedStateNames,
    ): LocalizableJsonApiError {
        return new LocalizableJsonApiError([
            'code' => self::CODE_INVALID_GOODS_RECEIPT_STATE_FOR_ACTION,
            'title' => [
                'en' => 'Invalid goods receipt state',
                'de' => 'Ungültiger Wareneingangsstatus',
            ],
            'detail' => [
                'en' => sprintf(
                    'The action cannot be executed because the goods receipt is in an incorrect state for that action. '
                    . 'Current state is "%s". Valid states are %s.',
                    $actualStateName,
                    implode(
                        ', ',
                        array_map(fn(string $stateName) => sprintf('"%s"', $stateName), $expectedStateNames),
                    ),
                ),
                'de' => sprintf(
                    'Die Aktion kann nicht ausgeführt werden, weil sich der Wareneingang in einem falschen Status ' .
                    'für diese Aktion befindet. Der aktuelle Status ist "%s". Gültige Status sind %s.',
                    $actualStateName,
                    implode(
                        ', ',
                        array_map(fn(string $stateName) => sprintf('"%s"', $stateName), $expectedStateNames),
                    ),
                ),
            ],
            'meta' => [
                'goodsReceiptId' => $goodsReceiptId,
                'actualStateName' => $actualStateName,
                'expectedStateNames' => $expectedStateNames,
            ],
        ]);
    }

    public static function goodsReceiptStillContainsStock(string $goodsReceiptId): LocalizableJsonApiError
    {
        return new LocalizableJsonApiError([
            'code' => self::CODE_GOODS_RECEIPT_STILL_CONTAINS_STOCK,
            'title' => [
                'en' => 'Goods receipt still contains stock',
                'de' => 'Wareneingang enthält noch Bestand',
            ],
            'detail' => [
                'en' => sprintf('The goods receipt with ID %s still contains stock.', $goodsReceiptId),
                'de' => sprintf('Der Wareneingang mit der ID %s enthält noch Bestand.', $goodsReceiptId),
            ],
            'meta' => ['goodsReceiptId' => $goodsReceiptId],
        ]);
    }

    public static function roundingConfigurationMissing(string $goodsReceiptId): LocalizableJsonApiError
    {
        return new LocalizableJsonApiError([
            'code' => self::CODE_ROUNDING_CONFIGURATION_MISSING,
            'title' => [
                'en' => 'Goods receipt is the missing rounding configuration',
                'de' => 'Wareneingang fehlt die Rundungskonfiguration',
            ],
            'detail' => [
                'en' => sprintf(
                    'The goods receipt with ID %s is missing the rounding configuration.',
                    $goodsReceiptId,
                ),
                'de' => sprintf(
                    'Dem Wareneingang mit der ID %s fehlt die Rundungskonfiguration.',
                    $goodsReceiptId,
                ),
            ],
            'meta' => [
                'goodsReceiptId' => $goodsReceiptId,
            ],
        ]);
    }

    public static function goodsReceiptNotFound(string $goodsReceiptId): LocalizableJsonApiError
    {
        return new LocalizableJsonApiError([
            'code' => self::CODE_GOODS_RECEIPT_NOT_FOUND,
            'title' => [
                'en' => 'Goods receipt not found',
                'de' => 'Wareneingang nicht gefunden',
            ],
            'detail' => [
                'en' => sprintf('The goods receipt with ID "%s" was not found.', $goodsReceiptId),
                'de' => sprintf('Der Wareneingang mit der ID "%s" wurde nicht gefunden.', $goodsReceiptId),
            ],
            'meta' => ['goodsReceiptId' => $goodsReceiptId],
        ]);
    }

    public static function missingWarehouse(string $returnOrderId, string $returnOrderNumber): LocalizableJsonApiError
    {
        return new LocalizableJsonApiError([
            'code' => self::CODE_MISSING_WAREHOUSE,
            'title' => [
                'en' => 'Missing warehouse',
                'de' => 'Fehlendes Lager',
            ],
            'detail' => [
                'en' => sprintf('The return order "%s" does not have a warehouse anymore.', $returnOrderNumber),
                'de' => sprintf('Der Retoure "%s" ist keinem Lager mehr zugeordnet.', $returnOrderNumber),
            ],
            'meta' => [
                'returnOrderId' => $returnOrderId,
                'returnOrderNumber' => $returnOrderNumber,
            ],
        ]);
    }

    public static function goodsReceiptContainedInStockValuationReport(string $goodsReceiptId): LocalizableJsonApiError
    {
        return new LocalizableJsonApiError([
            'code' => self::CODE_GOODS_RECEIPT_PART_OF_STOCK_VALUATION,
            'title' => [
                'en' => 'The goods receipt is part of in a stock valuation report',
                'de' => 'Der Wareneingang ist Teil eines bewerteten Warenbestands',
            ],
            'detail' => [
                'en' => sprintf('The goods receipt with ID "%s" is part of a stock valuation report, therefore its warehouse can\'t be changed.', $goodsReceiptId),
                'de' => sprintf('Der Wareneingang mit der ID "%s" ist Teil eines bewerteten Warenbestands, daher kann sein Ziellager nicht geändert werden.', $goodsReceiptId),
            ],
            'meta' => [
                'goodsReceiptId' => $goodsReceiptId,
            ],
        ]);
    }

    public static function goodsReceiptLineItemQuantityInvalid(int $maxQuantity): LocalizableJsonApiError
    {
        return new LocalizableJsonApiError([
            'title' => [
                'en' => 'Invalid goods receipt line item quantity',
                'de' => 'Ungültige Menge für Wareneingangsposition',
            ],
            'detail' => [
                'en' => sprintf('The quantity of the goods receipt line item must be between 1 and %d.', $maxQuantity),
                'de' => sprintf('Die Menge der Wareneingangsposition muss zwischen 1 und %d liegen.', $maxQuantity),
            ],
            'meta' => [
                'maxQuantity' => $maxQuantity,
            ],
        ]);
    }
}
