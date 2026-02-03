<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\PosAccountingDocument;

use Pickware\DatevBundle\EntryBatch\EntryBatchLogMessage;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportLogEntryDefinition;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class PosAccountingDocumentMessage extends EntryBatchLogMessage
{
    public static function createPosNotAvailableMessage(): self
    {
        return new self(
            content: [
                'de-DE' => 'Während dem Export war das Plugin Pickware POS entweder nicht installiert/aktiviert, oder in zu alter Version vorhanden. Für einige Komponenten dieses Exports wird das Plugin Pickware POS in der neuesten Version benötigt. Daher enthält dieser Export keine Detailinformationen über Pickware POS Filialen.',
                'en-GB' => 'During this export the Pickware POS plugin was not installed/active or available in a version that is too old. For some components of this export, the plugin Pickware POS is required in the newest versions. This export thus does not contain detail information about Pickware POS branch stores.',
            ],
            meta: [],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_ERROR,
        );
    }

    public static function createAccountUnresolvedForReceiptMessage(
        string $orderNumber,
        ?string $receiptNumber,
        ?float $taxRatePercentage,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Das Erlöskonto für den Steuersatz "%1$.2f%%" im Verkaufsbeleg "%2$s" der Bestellung "%3$s" konnte nicht ermittelt werden. Bitte überprüfe die Konten-Konfiguration für den Steuersatz "%1$.2f%%".',
                    $taxRatePercentage ?? 0.0,
                    $receiptNumber,
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'The account assignment for the tax rate "%1$.2f%%" for the receipt "%2$s" in the order "%3$s" could not be resolved. Please check your account configuration for the tax rate "%1$.2f%%".',
                    $taxRatePercentage ?? 0.0,
                    $receiptNumber,
                    $orderNumber,
                ),
            ],
            meta: [
                'orderNumber' => $orderNumber,
                'receiptNumber' => $receiptNumber,
                'taxRatePercentage' => $taxRatePercentage,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_ERROR,
        );
    }

    public static function createContraAccountUnresolvedForReceiptMessage(
        string $orderNumber,
        ?string $receiptNumber,
        ?float $taxRatePercentage,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Das Gegenkonto für den Steuersatz "%1$.2f%%" im Verkaufsbeleg "%2$s" der Bestellung "%3$s" konnte nicht ermittelt werden. Bitte überprüfe die Konten-Konfiguration für den Steuersatz "%1$.2f%%".',
                    $taxRatePercentage ?? 0.0,
                    $receiptNumber,
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'The contra account assignment for the tax rate "%1$.2f%%" for the receipt "%2$s" in the order "%3$s" could not be resolved. Please check your account configuration for the tax rate "%1$.2f%%".',
                    $taxRatePercentage ?? 0.0,
                    $receiptNumber,
                    $orderNumber,
                ),
            ],
            meta: [
                'orderNumber' => $orderNumber,
                'receiptNumber' => $receiptNumber,
                'taxRatePercentage' => $taxRatePercentage,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_ERROR,
        );
    }

    public static function createNoReceiptsMessage(string $orderNumber): self
    {
        return new self(
            content: [
                'de-DE' => sprintf('Für Bestellung "%s" wurde kein Verkaufsbeleg gefunden. Die Bestellung wurde nicht exportiert.', $orderNumber),
                'en-GB' => sprintf('No receipt was found for order "%s". The order was not exported.', $orderNumber),
            ],
            meta: ['orderNumber' => $orderNumber],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_ERROR,
        );
    }

    public static function createMoreThanOneReceiptMessage(string $orderNumber): self
    {
        return new self(
            content: [
                'de-DE' => sprintf('Für Bestellung "%s" wurde mehr als ein Verkaufsbeleg gefunden. Die Forderung wurde lediglich einmal exportiert.', $orderNumber),
                'en-GB' => sprintf('More than one receipt was found for order "%s". The claim was only exported once.', $orderNumber),
            ],
            meta: ['orderNumber' => $orderNumber],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_INFO,
        );
    }

    public static function createInvoiceExistsMessage(string $orderNumber, ?string $invoiceNumber, ?string $receiptNumber): self
    {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Für Bestellung %s gibt es zusätzlich zu dem Kassenbon "%s" eine Rechnung "%s". Die Forderung wurde mit Referenz auf den Kassenbon exportiert.',
                    $orderNumber,
                    $receiptNumber ?? 'unbekannter Kassenbon',
                    $invoiceNumber ?? 'unbekannte Rechnung',
                ),
                'en-GB' => sprintf(
                    'There is an invoice "%s" for order %s in addition to the receipt "%s". Only the claim of the receipt was exported. The claim was exported with a reference to the receipt',
                    $invoiceNumber ?? 'unknown invoice',
                    $orderNumber,
                    $receiptNumber ?? 'unknown receipt',
                ),
            ],
            meta: ['orderNumber' => $orderNumber],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_INFO,
        );
    }

    public static function createAccountUnresolvedForReturnReceiptMessage(
        string $returnOrderNumber,
        ?string $returnReceiptNumber,
        ?float $taxRatePercentage,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Das Erlöskonto für den Steuersatz "%1$.2f%%" im Rückgabebeleg "%2$s" der Rückgabe "%3$s" konnte nicht ermittelt werden. Bitte überprüfe die Konten-Konfiguration für den Steuersatz "%1$.2f%%".',
                    $taxRatePercentage ?? 0.0,
                    $returnReceiptNumber,
                    $returnOrderNumber,
                ),
                'en-GB' => sprintf(
                    'The account assignment for the tax rate "%1$.2f%%" for the return receipt "%2$s" of the return order "%3$s" could not be resolved. Please check your account configuration for the tax rate "%1$.2f%%".',
                    $taxRatePercentage ?? 0.0,
                    $returnReceiptNumber,
                    $returnOrderNumber,
                ),
            ],
            meta: [
                'returnOrderNumber' => $returnOrderNumber,
                'returnReceiptNumber' => $returnReceiptNumber,
                'taxRatePercentage' => $taxRatePercentage,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_ERROR,
        );
    }

    public static function createContraAccountUnresolvedForReturnReceiptMessage(
        string $returnOrderNumber,
        ?string $returnReceiptNumber,
        ?float $taxRatePercentage,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Das Gegenkonto für den Steuersatz "%1$.2f%%" im Rückgabebeleg "%2$s" der Rückgabe "%3$s" konnte nicht ermittelt werden. Bitte überprüfe die Konten-Konfiguration für den Steuersatz "%1$.2f%%".',
                    $taxRatePercentage ?? 0.0,
                    $returnReceiptNumber,
                    $returnOrderNumber,
                ),
                'en-GB' => sprintf(
                    'The contra account assignment for the tax rate "%1$.2f%%" for the return receipt "%2$s" of the return order "%3$s" could not be resolved. Please check your account configuration for the tax rate "%1$.2f%%".',
                    $taxRatePercentage ?? 0.0,
                    $returnReceiptNumber,
                    $returnOrderNumber,
                ),
            ],
            meta: [
                'returnOrderNumber' => $returnOrderNumber,
                'returnReceiptNumber' => $returnReceiptNumber,
                'taxRatePercentage' => $taxRatePercentage,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_ERROR,
        );
    }

    public static function createNoReturnReceiptsMessage(string $returnOrderNumber): self
    {
        return new self(
            content: [
                'de-DE' => sprintf('Für Rückgabe "%s" wurde kein Rückgabebeleg gefunden. Die Rückgabe wurde nicht exportiert.', $returnOrderNumber),
                'en-GB' => sprintf('No return receipt was found for return order "%s". The return order was not exported.', $returnOrderNumber),
            ],
            meta: ['returnOrderNumber' => $returnOrderNumber],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_ERROR,
        );
    }

    public static function createMoreThanOneReturnReceiptMessage(string $returnOrderNumber): self
    {
        return new self(
            content: [
                'de-DE' => sprintf('Für Rückgabe "%s" wurde mehr als ein Rückgabebeleg gefunden. Die Forderung wurde lediglich einmal exportiert.', $returnOrderNumber),
                'en-GB' => sprintf('More than one return receipt was found for return order "%s". The claim was only exported once.', $returnOrderNumber),
            ],
            meta: ['returnOrderNumber' => $returnOrderNumber],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_INFO,
        );
    }
}
