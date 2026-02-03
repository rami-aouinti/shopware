<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\CashPointClosing\ApiVersioning\ApiVersion20230308;

use stdClass;

trait CashPointClosingTransactionModifying
{
    private function removeFiscalizationContext(array &$cashPointClosingTransaction): void
    {
        if (
            !array_key_exists('fiscalizationContext', $cashPointClosingTransaction)
            || $cashPointClosingTransaction['fiscalizationContext'] === null
            || !array_key_exists('fiskalyDe', $cashPointClosingTransaction['fiscalizationContext'])
        ) {
            return;
        }

        $fiskalyDeContext = $cashPointClosingTransaction['fiscalizationContext']['fiskalyDe'];
        if ($fiskalyDeContext) {
            $cashPointClosingTransaction['cashRegisterFiskalyClientUuid'] = $fiskalyDeContext['clientUuid'];
            if (array_key_exists('error', $fiskalyDeContext['result'])) {
                $cashPointClosingTransaction['fiskalyTssErrorMessage'] = $fiskalyDeContext['result']['error'];
            }
            if (array_key_exists('tssTransactionUuid', $fiskalyDeContext['result'])) {
                $cashPointClosingTransaction['fiskalyTssTransactionUuid'] = $fiskalyDeContext['result']['tssTransactionUuid'];
            }
        }
        unset($cashPointClosingTransaction['fiscalizationContext']);
    }

    private function addFiscalizationContext(int|float|bool|array|stdClass &$cashPointClosingTransaction): void
    {
        if (
            property_exists($cashPointClosingTransaction, 'cashRegisterFiskalyClientUuid')
            && $cashPointClosingTransaction->cashRegisterFiskalyClientUuid !== null
        ) {
            $cashPointClosingTransaction->fiscalizationContext = [
                'fiskalyDe' => [
                    'clientUuid' => $cashPointClosingTransaction->cashRegisterFiskalyClientUuid,
                ],
            ];

            if (
                property_exists($cashPointClosingTransaction, 'fiskalyTssTransactionUuid')
                && $cashPointClosingTransaction->fiskalyTssTransactionUuid !== null
            ) {
                $cashPointClosingTransaction->fiscalizationContext['fiskalyDe']['result'] = [
                    'tssTransactionUuid' => $cashPointClosingTransaction->fiskalyTssTransactionUuid,
                ];
            } elseif (
                property_exists($cashPointClosingTransaction, 'fiskalyTssErrorMessage')
                && $cashPointClosingTransaction->fiskalyTssErrorMessage !== null
            ) {
                $cashPointClosingTransaction->fiscalizationContext['fiskalyDe']['result'] = [
                    'error' => $cashPointClosingTransaction->fiskalyTssErrorMessage,
                ];
            }
        }

        unset(
            $cashPointClosingTransaction->cashRegisterFiskalyClientUuid,
            $cashPointClosingTransaction->fiskalyTssTransactionUuid,
            $cashPointClosingTransaction->fiskalyTssErrorMessage,
        );
    }

    private function addFiscalizationContextToCriteriaIncludes(int|float|bool|array|stdClass &$jsonContent): void
    {
        $transactionProperties = $jsonContent->includes->pickware_pos_cash_point_closing_transaction;
        $transactionProperties[] = 'fiscalizationContext';
        $transactionProperties = array_values(array_filter(
            $transactionProperties,
            fn($value) => $value !== 'fiskalyTssTransactionUuid' && $value !== 'fiskalyTssErrorMessage' && $value !== 'cashRegisterFiskalyClientUuid',
        ));
        $jsonContent->includes->pickware_pos_cash_point_closing_transaction = $transactionProperties;
    }
}
