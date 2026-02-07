<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Config\AccountAssignment;

use Pickware\DatevBundle\EntryBatch\EntryBatchLogMessage;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportLogEntryDefinition;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class AccountAssignmentMessage extends EntryBatchLogMessage
{
    public static function createTaxFreeGermanyFallbackMessage(string $orderNumber): self
    {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Bei Bestellung "%s" handelt es sich um eine steuerfreie Lieferung nach Deutschland. Daher wurde der Erlös analog zu einem Produkt mit Steuersatz 0%% kontiert. Bitte prüfe deine Steuereinstellungen!',
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'Order "%s" is a tax-free delivery to Germany. Therefore, its proceeds were assigned to an account analogous to a product with a tax rate of 0%%. Please check your tax settings!',
                    $orderNumber,
                ),
            ],
            meta: ['orderNumber' => $orderNumber],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_INFO,
        );
    }

    public static function createTaxFreeIntraCommunityFallbackMessage(string $orderNumber): self
    {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Bei Bestellung "%s" handelt es sich um eine steuerfreie, innergemeinschaftliche Lieferung. Der Kunde hat keine Steuernummer, aber eine Firma in der Rechnungsadresse ausgefüllt. Daher wurde der Erlös analog zu einem Produkt mit Steuersatz 0%% für das Land kontiert. Bitte prüfe dieses Konto in DATEV!',
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'Order "%s" is a tax-free, intra-community delivery. The customer has no vat ID, but entered a company in the billing address. Therefore, its proceeds were assigned to an account analogous to a product with a tax rate of 0%% for the country. Please check the assigned account in DATEV!',
                    $orderNumber,
                ),
            ],
            meta: ['orderNumber' => $orderNumber],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_INFO,
        );
    }

    public static function createCustomerNumberDoesNotMatchIndividualDebtorFormatMessage(string $customerNumber): self
    {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Die Kundennummer "%s" entspricht nicht den DATEV Formatvorgaben, da sie nicht-numerische Werte enthält oder länger als 8 Ziffern ist. Anstelle der Kundennummer wurde daher das Standarddebitorenkonto verwendet. Ändere die Kundennummer, wenn diese als Einzeldebitor verwendet werden soll.',
                    $customerNumber,
                ),
                'en-GB' => sprintf(
                    'The customer number "%s" does not meet the DATEV format specifications, as it contains non-numeric values or is longer than 8 digits. Therefore the standard debtor account was used instead of the customer number. Change the customer number if it is to be used as a individual debtor.',
                    $customerNumber,
                ),
            ],
            meta: ['customerNumber' => $customerNumber],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_INFO,
        );
    }

    public static function createCustomerSpecificDebtorAccountDoesNotMatchIndividualDebtorFormatMessage(
        string $customerSpecificAccount,
        string $customerNumber,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Das kundenspezifische Debitorenkonto "%s" des Kunden "%s" entspricht nicht den DATEV Formatvorgaben, da das Konto nicht-numerische Werte enthält oder länger als 8 Ziffern ist. Anstelle des kundenspezifischen Debitorenkontos wurde daher das Standarddebitorenkonto verwendet.',
                    $customerSpecificAccount,
                    $customerNumber,
                ),
                'en-GB' => sprintf(
                    'The customer-specific debtor account "%s" of customer "%s" does not meet the DATEV format specifications, as the account contains non-numeric values or is longer than 8 digits. The standard debtor account was therefore used instead of the customer-specific debtor account.',
                    $customerSpecificAccount,
                    $customerNumber,
                ),
            ],
            meta: [
                'customerSpecificAccount' => $customerSpecificAccount,
                'customerNumber' => $customerNumber,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_INFO,
        );
    }

    public static function createUnknownShippingAddressCountryFormatMessage(
        string $orderNumber,
        string $documentType,
        string $documentNumber,
    ): self {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Das Lieferland des Dokuments "%s" mit der Nummer "%s" der Bestellung "%s" konnte nicht ermittelt werden, weil die Lieferadresse ungültig ist. Bei der Ermittlung der Erlöskonten wurden daher nur die Standarderlöskonten berücksichtigt.',
                    $documentType,
                    $documentNumber,
                    $orderNumber,
                ),
                'en-GB' => sprintf(
                    'The delivery country of the document "%s" with the number "%s" of the order "%s" could not be determined because the delivery address is invalid. Only the standard revenue accounts were therefore taken into account when determining the revenue accounts.',
                    $documentType,
                    $documentNumber,
                    $orderNumber,
                ),
            ],
            meta: [
                'orderNumber' => $orderNumber,
                'documentType' => $documentType,
                'documentNumber' => $documentNumber,
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_WARNING,
        );
    }
}
