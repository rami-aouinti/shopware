<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\CompanyCodes;

use Pickware\DatevBundle\EntryBatch\EntryBatchLogMessage;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportLogEntryDefinition;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class CompanyCodeMessage extends EntryBatchLogMessage
{
    public static function createCompanyCodeDoesNotMatchFormatMessage(int $companyCode, CompanyCodeMessageMetadata $metadata): self
    {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Der Standardbuchungskreis "%s" des Verkaufskanals "%s" entspricht nicht den DATEV Formatvorgaben. Buchungskreise müssen exakt 2 Ziffern lang sein und dürfen keine nicht-numerischen Werte enthalten. Die Erlöskonten für das Dokument "%s" mit der Nummer "%s" der Bestellung "%s" wurden daher nicht um einen Buchungskreis erweitert. Bitte überprüfe Deine DATEV Konfiguration.',
                    $companyCode,
                    $metadata->getSalesChannelName(),
                    $metadata->getDocumentType(),
                    $metadata->getDocumentNumber(),
                    $metadata->getOrderNumber(),
                ),
                'en-GB' => sprintf(
                    'The default company code “%s” of the sales channel “%s” does not meet the the DATEV format specifications. Company codes must be exactly 2 digits long and may not contain any non-numeric values. The revenue accounts for the document “%s” with the number “%s” of the order “%s” have therefore not been extended by a company code. Please check your DATEV configuration.',
                    $companyCode,
                    $metadata->getSalesChannelName(),
                    $metadata->getDocumentType(),
                    $metadata->getDocumentNumber(),
                    $metadata->getOrderNumber(),
                ),
            ],
            meta: [
                'companyCode' => $companyCode,
                'salesChannelName' => $metadata->getSalesChannelName(),
                'documentType' => $metadata->getDocumentType(),
                'documentNumber' => $metadata->getDocumentNumber(),
                'orderNumber' => $metadata->getOrderNumber(),
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_INFO,
        );
    }

    public static function createCustomerGroupSpecificCompanyCodeDoesNotMatchFormatMessage(string $companyCode, CompanyCodeMessageMetadata $metadata): self
    {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Der kundengruppenspezifische Buchungskreis "%s" für Kunden der Gruppe "%s" entspricht nicht den DATEV Formatvorgaben. Buchungskreise müssen exakt 2 Ziffern lang sein und dürfen keine nicht-numerischen Werte enthalten. Der Standardbuchungskreis des Verkaufskanals "%s" wurde daher zur Bestimmung der Erlöskonten für das Dokument "%s" mit der Nummer "%s" der Bestellung "%s" verwendet. Bitte überprüfe Deine DATEV Konfiguration.',
                    $companyCode,
                    $metadata->getCustomerGroupName(),
                    $metadata->getSalesChannelName(),
                    $metadata->getDocumentType(),
                    $metadata->getDocumentNumber(),
                    $metadata->getOrderNumber(),
                ),
                'en-GB' => sprintf(
                    'The customer-group-specific company code “%s” for customers of group "%s" does not meet the the DATEV format specifications. Company codes must be exactly 2 digits long and may not contain any non-numeric values. The default company code of the sales channel “%s” was therefore used to determine the revenue accounts for the document “%s” with the number “%s” of order “%s”. Please check your DATEV configuration.',
                    $companyCode,
                    $metadata->getCustomerGroupName(),
                    $metadata->getSalesChannelName(),
                    $metadata->getDocumentType(),
                    $metadata->getDocumentNumber(),
                    $metadata->getOrderNumber(),
                ),
            ],
            meta: [
                'companyCode' => $companyCode,
                'customerGroupName' => $metadata->getCustomerGroupName(),
                'salesChannelName' => $metadata->getSalesChannelName(),
                'documentType' => $metadata->getDocumentType(),
                'documentNumber' => $metadata->getDocumentNumber(),
                'orderNumber' => $metadata->getOrderNumber(),
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_INFO,
        );
    }

    public static function createCustomerSpecificCompanyCodeDoesNotMatchFormatMessage(string $companyCode, CompanyCodeMessageMetadata $metadata): self
    {
        return new self(
            content: [
                'de-DE' => sprintf(
                    'Der kundenspezifische Buchungskreis "%s" des Kunden mit der Nummer "%s" entspricht nicht den DATEV Formatvorgaben. Buchungskreise müssen exakt 2 Ziffern lang sein und dürfen keine nicht-numerischen Werte enthalten. Der Standardbuchungskreis des Verkaufskanals "%s" wurde daher zur Bestimmung der Erlöskonten für das Dokument "%s" mit der Nummer "%s" der Bestellung "%s" verwendet. Bitte überprüfe Deine DATEV Konfiguration.',
                    $companyCode,
                    $metadata->getCustomerNumber(),
                    $metadata->getSalesChannelName(),
                    $metadata->getDocumentType(),
                    $metadata->getDocumentNumber(),
                    $metadata->getOrderNumber(),
                ),
                'en-GB' => sprintf(
                    'The customer-specific company code “%s” of the customer with customer number “%s” does not meet the the DATEV format specifications. Company codes must be exactly 2 digits long and may not contain any non-numeric values. The default company code of the sales channel “%s” was therefore used to determine the revenue accounts for the document “%s” with the number “%s” of order “%s”. Please check your DATEV configuration.',
                    $companyCode,
                    $metadata->getCustomerNumber(),
                    $metadata->getSalesChannelName(),
                    $metadata->getDocumentType(),
                    $metadata->getDocumentNumber(),
                    $metadata->getOrderNumber(),
                ),
            ],
            meta: [
                'companyCode' => $companyCode,
                'customerNumber' => $metadata->getCustomerNumber(),
                'salesChannelName' => $metadata->getSalesChannelName(),
                'documentType' => $metadata->getDocumentType(),
                'documentNumber' => $metadata->getDocumentNumber(),
                'orderNumber' => $metadata->getOrderNumber(),
            ],
            logLevel: ImportExportLogEntryDefinition::LOG_LEVEL_INFO,
        );
    }
}
