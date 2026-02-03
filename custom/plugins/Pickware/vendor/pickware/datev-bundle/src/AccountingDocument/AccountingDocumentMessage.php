<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\AccountingDocument;

use Pickware\DatevBundle\EntryBatch\EntryBatchLogMessage;
use Pickware\DatevBundle\PickwareDatevBundle;
use Pickware\PickwareErpStarter\ImportExport\ImportExportLogLevel;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportLogEntryDefinition;
use Pickware\PickwareErpStarter\InvoiceCorrection\InvoiceCorrectionDocumentType;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AccountingDocumentMessage extends EntryBatchLogMessage
{
    public static function createAccountUnresolvedError(
        string $orderNumber,
        ?string $documentNumber,
        ?float $taxRatePercentage,
        string $documentType,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Das Erlöskonto für den Steuersatz "%1$.2f%%" im Dokument "%2$s" mit der Nummer "%3$s" der Bestellung "%4$s" konnte nicht ermittelt werden. Bitte überprüfe die Konten-Konfiguration für den Steuersatz "%1$.2f%%".',
                    $taxRatePercentage ?? 0.0,
                    self::getDocumentTypeTranslation('de-DE', $documentType),
                    $documentNumber,
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'The account assignment for the tax rate "%1$.2f%%" for the "%2$s" document with number "%3$s" in the order "%4$s" could not be resolved. Please check your account configuration for the tax rate "%1$.2f%%".',
                    $taxRatePercentage ?? 0.0,
                    self::getDocumentTypeTranslation('en-GB', $documentType),
                    $documentNumber,
                    $orderNumber,
                ),
            ],
            meta: [
                'orderNumber' => $orderNumber,
                'documentNumber' => $documentNumber,
                'taxRatePercentage' => $taxRatePercentage,
                'documentType' => $documentType,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_ERROR,
        );
    }

    public static function createContraAccountUnresolvedError(
        string $orderNumber,
        ?string $documentNumber,
        ?float $taxRatePercentage,
        string $documentType,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Das Gegenkonto für den Steuersatz "%1$.2f%%" im Dokument "%2$s" mit der Nummer "%3$s" der Bestellung "%4$s" konnte nicht ermittelt werden. Bitte überprüfe die Konten-Konfiguration für den Steuersatz "%1$.2f%%".',
                    $taxRatePercentage ?? 0.0,
                    self::getDocumentTypeTranslation('de-DE', $documentType),
                    $documentNumber,
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'The account assignment for the tax rate "%1$.2f%%" for the "%2$s" document with number "%3$s" in the order "%4$s" could not be resolved. Please check your account configuration for the tax rate "%1$.2f%%".',
                    $taxRatePercentage ?? 0.0,
                    self::getDocumentTypeTranslation('en-GB', $documentType),
                    $documentNumber,
                    $orderNumber,
                ),
            ],
            meta: [
                'orderNumber' => $orderNumber,
                'documentNumber' => $documentNumber,
                'taxRatePercentage' => $taxRatePercentage,
                'documentType' => $documentType,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_ERROR,
        );
    }

    public static function createDocumentHasNoOrderVersionError(
        string $orderNumber,
        ?string $documentNumber,
        string $documentType,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Die Daten des Dokuments "%s" mit der Nummer "%s" der Bestellung "%s" sind unvollständig. Das Dokument kann daher nicht nach DATEV exportiert werden.',
                    self::getDocumentTypeTranslation('de-DE', $documentType),
                    $documentNumber,
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'The document data for document “%s” with number “%s” for order “%s” is incomplete. The document can therefore not be exported to DATEV.',
                    self::getDocumentTypeTranslation('en-GB', $documentType),
                    $documentNumber,
                    $orderNumber,
                ),
            ],
            meta: [
                'orderNumber' => $orderNumber,
                'documentNumber' => $documentNumber,
                'documentType' => $documentType,
            ],
            logLevel: ImportExportLogLevel::Error->value,
        );
    }

    public static function createDocumentDateMissingWarning(
        string $orderNumber,
        ?string $documentNumber,
        string $documentType,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Das Dokumentendatum für das Dokument "%s" mit der Nummer "%s" in der Bestellung "%s" fehlt. Stattdessen wurde das Datum an dem das Dokument erstellt wurde verwendet.',
                    self::getDocumentTypeTranslation('de-DE', $documentType),
                    $documentNumber,
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'The document date for the "%s" document with the number "%s" in the order "%s" is missing. The document creation date was used instead.',
                    self::getDocumentTypeTranslation('en-GB', $documentType),
                    $documentNumber,
                    $orderNumber,
                ),
            ],
            meta: [
                'orderNumber' => $orderNumber,
                'documentNumber' => $documentNumber,
                'documentType' => $documentType,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_WARNING,
        );
    }

    public static function createNoShippingAddressInfo(
        string $orderNumber,
        ?string $documentNumber,
        string $documentType,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Die Bestellung "%s" für das Dokument "%s" mit der Nummer "%s" hat keine Lieferadresse. Stattdessen wird die Rechnungsadresse verwendet.',
                    $orderNumber,
                    self::getDocumentTypeTranslation('de-DE', $documentType),
                    $documentNumber,
                ),
                'en-GB' => sprintf(
                    'The order "%s" for the "%s" document with number "%s" has no shipping address. The billing address will be used instead.',
                    $orderNumber,
                    self::getDocumentTypeTranslation('en-GB', $documentType),
                    $documentNumber,
                ),
            ],
            meta: [
                'orderNumber' => $orderNumber,
                'documentNumber' => $documentNumber,
                'documentType' => $documentType,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_INFO,
        );
    }

    public static function createIncompleteTaxInformationWarning(
        string $orderNumber,
        ?string $documentNumber,
        string $documentType,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Die Steuerinformationen für das Dokument "%s" mit der Nummer "%s" der Bestellung "%s" sind unvollständig. Für das Dokument konnten daher keine Buchungssätze erzeugt werden.',
                    self::getDocumentTypeTranslation('de-DE', $documentType),
                    $documentNumber,
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'The tax information for the "%s" document with number "%s" of the order "%s" are incomplete. No posting records were therefore created for the document.',
                    self::getDocumentTypeTranslation('en-GB', $documentType),
                    $documentNumber,
                    $orderNumber,
                ),
            ],
            meta: [
                'orderNumber' => $orderNumber,
                'documentNumber' => $documentNumber,
                'documentType' => $documentType,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_WARNING,
        );
    }

    public static function createTaskNumberMaxLengthExceededWarning(
        string $taskNumber,
        string $orderNumber,
        ?string $documentNumber,
        string $documentType,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Die Auftragsnummer "%s" für das Dokument "%s" mit der Nummer "%s" der Bestellung "%s" ist zu lang. Nummer der Bestellung wird als Fallback verwendet.',
                    $taskNumber,
                    self::getDocumentTypeTranslation('de-DE', $documentType),
                    $documentNumber,
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'The Auftragsnummer "%s" for the "%s" document with number "%s" of the order "%s" is too long. Order number will be used as fallback.',
                    $taskNumber,
                    self::getDocumentTypeTranslation('en-GB', $documentType),
                    $documentNumber,
                    $orderNumber,
                ),
            ],
            meta: [
                'taskNumber' => $taskNumber,
                'orderNumber' => $orderNumber,
                'documentNumber' => $documentNumber,
                'documentType' => $documentType,
            ],
            logLevel: ImportExportLogLevel::Warning->value,
        );
    }

    public static function createZugferdDocumentNoBelegbildWarning(
        string $orderNumber,
        string $documentNumber,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Der Belegexport unterstützt nur Belegbilder im PDF-Format. Das Dokument "Invoice: ZUGFeRD E-invoice" mit der Nummer "%s" der Bestellung "%s" ist daher nicht im Belegexport enthalten.',
                    $documentNumber,
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'The receipt export only supports document images in PDF format. The document “Invoice: ZUGFeRD E-invoice” with number “%s” for order “%s” is therefore not included in the receipt export.',
                    $documentNumber,
                    $orderNumber,
                ),
            ],
            meta: [
                'orderNumber' => $orderNumber,
                'documentNumber' => $documentNumber,
                'documentType' => ZugferdRenderer::TYPE,
            ],
            logLevel: ImportExportLogLevel::Warning->value,
        );
    }

    /**
     * @param 'de-DE'|'en-GB' $locale
     */
    private static function getDocumentTypeTranslation(string $locale, string $documentType): string
    {
        return match ($locale) {
            'de-DE' => match ($documentType) {
                InvoiceRenderer::TYPE => 'Rechnung',
                ZugferdRenderer::TYPE => 'E-Rechnung',
                ZugferdEmbeddedRenderer::TYPE => 'E-Rechnung (eingebettet)',
                StornoRenderer::TYPE => 'Stornorechnung',
                InvoiceCorrectionDocumentType::TECHNICAL_NAME => 'Rechnungskorrektur',
                PickwareDatevBundle::PICKWARE_POS_RECEIPT_DOCUMENT_TYPE_TECHNICAL_NAME => 'POS-Beleg',
                default => 'Unbekannter Dokumententyp',
            },
            'en-GB' => match ($documentType) {
                InvoiceRenderer::TYPE => 'invoice',
                ZugferdRenderer::TYPE => 'E-invoice',
                ZugferdEmbeddedRenderer::TYPE => 'E-invoice (embedded)',
                StornoRenderer::TYPE => 'storno bill',
                InvoiceCorrectionDocumentType::TECHNICAL_NAME => 'invoice correction',
                PickwareDatevBundle::PICKWARE_POS_RECEIPT_DOCUMENT_TYPE_TECHNICAL_NAME => 'POS receipt',
                default => 'unknown document type',
            },
        };
    }
}
