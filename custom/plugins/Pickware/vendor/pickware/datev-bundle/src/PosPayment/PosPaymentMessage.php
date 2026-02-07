<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PosPayment;

use Pickware\DatevBundle\EntryBatch\EntryBatchLogMessage;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportLogEntryDefinition;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PosPaymentMessage extends EntryBatchLogMessage
{
    public static function createPosNotAvailableMessage(): self
    {
        return new self(
            content: [
                'de-DE' => 'Während dem Export war das Plugin Pickware POS entweder nicht installiert/aktiviert, oder in zu alter Version vorhanden. Für einige Komponenten dieses Exports wird das Plugin Pickware POS in der neuesten Version benötigt. Daher enthält dieser Export keine Ein-/Auszahlungen, die am Pickware POS getätigt wurden, sowie keine Detailinformationen über Pickware POS Filialen.',
                'en-GB' => 'During this export the Pickware POS plugin was not installed/active or available in a version that is too old. For some components of this export, the plugin Pickware POS is required in the newest versions. This export thus does not contain deposits/withdrawals made with Pickware POS and no detail information about Pickware POS branch stores.',
            ],
            meta: [],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_ERROR,
        );
    }

    public static function createAccountUnresolvedForPaymentMessage(
        string $orderNumber,
        float $amount,
        string $currencyIsoCode,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Das Konto für die Zahlung der Bestellung "%s" in Höhe von %.2f %s konnte nicht ermittelt werden. Die Zahlung wurde nicht exportiert.',
                    $orderNumber,
                    $amount,
                    $currencyIsoCode,
                ),
                'en-GB' => sprintf(
                    'The account assignment for the payment of the order "%s" amounting to %.2f %s could not be resolved. The payment was not exported.',
                    $orderNumber,
                    $amount,
                    $currencyIsoCode,
                ),
            ],
            meta: [
                'orderNumber' => $orderNumber,
                'amount' => $amount,
                'currencyIsoCode' => $currencyIsoCode,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_ERROR,
        );
    }

    public static function createContraAccountUnresolvedForPaymentMessage(
        string $orderNumber,
        float $amount,
        string $currencyIsoCode,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Das Gegenkonto für die Zahlung der Bestellung "%s" in Höhe von %.2f %s konnte nicht ermittelt werden. Die Zahlung wurde nicht exportiert.',
                    $orderNumber,
                    $amount,
                    $currencyIsoCode,
                ),
                'en-GB' => sprintf(
                    'The contra account assignment for the payment of the order "%s" amounting to %.2f %s could not be resolved. The payment was not exported.',
                    $orderNumber,
                    $amount,
                    $currencyIsoCode,
                ),
            ],
            meta: [
                'orderNumber' => $orderNumber,
                'amount' => $amount,
                'currencyIsoCode' => $currencyIsoCode,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_ERROR,
        );
    }

    public static function createAccountUnresolvedForCashMovementMessage(
        string $branchStoreName,
        string $cashRegisterName,
        float $amount,
        string $currencyIsoCode,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Das Konto für die Ein-/Auszahlung aus der Filiale "%s" an der Kasse "%s" in Höhe von %.2f %s konnte nicht ermittelt werden. Die Ein-/Auszahlung wurde nicht exportiert.',
                    $branchStoreName,
                    $cashRegisterName,
                    $amount,
                    $currencyIsoCode,
                ),
                'en-GB' => sprintf(
                    'The account assignment for the deposit/withdrawal from the branch store "%s" at the cash register "%s" amounting to %.2f %s could not be resolved. The deposit/withdrawal was not exported.',
                    $branchStoreName,
                    $cashRegisterName,
                    $amount,
                    $currencyIsoCode,
                ),
            ],
            meta: [
                'branchStoreName' => $branchStoreName,
                'cashRegisterName' => $cashRegisterName,
                'amount' => $amount,
                'currencyIsoCode' => $currencyIsoCode,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_ERROR,
        );
    }

    public static function createContraAccountUnresolvedForCashMovementMessage(
        string $branchStoreName,
        string $cashRegisterName,
        float $amount,
        string $currencyIsoCode,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Das Gegenkonto für die Ein-/Auszahlung aus der Filiale "%s" an der Kasse "%s" in Höhe von %.2f %s konnte nicht ermittelt werden. Die Ein-/Auszahlung wurde nicht exportiert.',
                    $branchStoreName,
                    $cashRegisterName,
                    $amount,
                    $currencyIsoCode,
                ),
                'en-GB' => sprintf(
                    'The contra account assignment for the deposit/withdrawal from the branch store "%s" at the cash register "%s" amounting to %.2f %s could not be resolved. The deposit/withdrawal was not exported.',
                    $branchStoreName,
                    $cashRegisterName,
                    $amount,
                    $currencyIsoCode,
                ),
            ],
            meta: [
                'branchStoreName' => $branchStoreName,
                'cashRegisterName' => $cashRegisterName,
                'amount' => $amount,
                'currencyIsoCode' => $currencyIsoCode,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_ERROR,
        );
    }
}
