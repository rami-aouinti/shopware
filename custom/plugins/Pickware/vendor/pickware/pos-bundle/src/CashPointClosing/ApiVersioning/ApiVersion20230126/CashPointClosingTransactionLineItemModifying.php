<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing\ApiVersioning\ApiVersion20230126;

use Shopware\Core\Framework\Uuid\Uuid;
use stdClass;

trait CashPointClosingTransactionLineItemModifying
{
    private function removeVatTable(array &$cashPointClosingTransactionLineItem): void
    {
        $cashPointClosingTransactionLineItem['taxId'] = Uuid::randomHex(); // taxId is required by older clients, but in fact never used
        $cashPointClosingTransactionLineItem['taxRate'] = $cashPointClosingTransactionLineItem['vatTable'][0]['taxRate'];
        unset($cashPointClosingTransactionLineItem['vatTable']);
    }

    private function addVatTable(int|float|bool|array|stdClass &$cashPointClosingTransactionLineItem): void
    {
        $taxRate = $cashPointClosingTransactionLineItem->taxRate;
        $price = $cashPointClosingTransactionLineItem->total->inclVat;
        $tax = $cashPointClosingTransactionLineItem->total->vat;
        $cashPointClosingTransactionLineItem->vatTable = [
            [
                'tax' => $tax,
                'taxRate' => $taxRate,
                'price' => $price,
            ],
        ];
        unset($cashPointClosingTransactionLineItem->taxRate);
        unset($cashPointClosingTransactionLineItem->taxId);
    }

    private function includeVatTableInRequest(int|float|bool|array|stdClass &$jsonContent): void
    {
        $lineItemProperties = $jsonContent->includes->pickware_pos_cash_point_closing_transaction_line_item;
        $lineItemProperties[] = 'vatTable';
        $lineItemProperties = array_values(array_filter(
            $lineItemProperties,
            fn($value) => $value !== 'taxRate' && $value !== 'taxId',
        ));
        $jsonContent->includes->pickware_pos_cash_point_closing_transaction_line_item = $lineItemProperties;
    }
}
