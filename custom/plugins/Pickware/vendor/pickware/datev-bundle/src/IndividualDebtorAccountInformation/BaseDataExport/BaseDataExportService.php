<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\IndividualDebtorAccountInformation\BaseDataExport;

use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\Config\ConfigService;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchExportConfig;
use Pickware\DatevBundle\GenericEXTFCSVExport\EXTFCSVColumnType;
use Pickware\DatevBundle\GenericEXTFCSVExport\EXTFCSVExportFormat;
use Pickware\DatevBundle\GenericEXTFCSVExport\EXTFCSVService;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class BaseDataExportService
{
    public const COLUMN_ACCOUNT = 'Konto';
    public const COLUMN_NAME_OF_COMPANY = 'Name (Adressatentyp Unternehmen)';
    public const COLUMN_LAST_NAME_OF_PERSON = 'Name (Adressatentyp natürl. Person)';
    public const COLUMN_FIRST_NAME_OF_PERSON = 'Vorname (Adressatentyp natürl. Person)';
    public const COLUMN_ACCOUNT_TYPE = 'Adressatentyp';
    public const COLUMN_EU_COUNTRY_CODE = 'EU-Mitgliedstaat';
    public const COLUMN_EU_VAT_ID = 'EU-USt-IdNr.';
    public const COLUMN_SALUTATION = 'Anrede';
    public const COLUMN_STREET = 'Straße';
    public const COLUMN_ZIP_CODE = 'Postleitzahl';
    public const COLUMN_CITY = 'Ort';
    public const COLUMN_COUNTRY = 'Land';
    public const COLUMN_PHONE_NUMBER = 'Telefon';
    public const COLUMN_EMAIL = 'E-Mail';
    public const COLUMN_CUSTOMER_NUMBER = 'Kundennummer';
    public const EMPTY_COLUMN_LABEL = 'Leerfeld';
    private const EXPORTED_COLUMNS = [
        self::COLUMN_ACCOUNT,
        self::COLUMN_NAME_OF_COMPANY,
        self::COLUMN_LAST_NAME_OF_PERSON,
        self::COLUMN_FIRST_NAME_OF_PERSON,
        self::COLUMN_ACCOUNT_TYPE,
        self::COLUMN_EU_COUNTRY_CODE,
        self::COLUMN_EU_VAT_ID,
        self::COLUMN_SALUTATION,
        self::COLUMN_STREET,
        self::COLUMN_ZIP_CODE,
        self::COLUMN_CITY,
        self::COLUMN_COUNTRY,
        self::COLUMN_PHONE_NUMBER,
        self::COLUMN_EMAIL,
        self::COLUMN_CUSTOMER_NUMBER,
    ];
    public const COLUMN_LABEL_TYPE_MAPPING = [
        self::COLUMN_ACCOUNT => EXTFCSVColumnType::Int,
        self::COLUMN_NAME_OF_COMPANY => EXTFCSVColumnType::String,
        'Unternehmensgegenstand' => EXTFCSVColumnType::String,
        self::COLUMN_LAST_NAME_OF_PERSON => EXTFCSVColumnType::String,
        self::COLUMN_FIRST_NAME_OF_PERSON => EXTFCSVColumnType::String,
        'Name (Adressatentyp keine Angabe)' => EXTFCSVColumnType::String,
        self::COLUMN_ACCOUNT_TYPE => EXTFCSVColumnType::String,
        'Kurzbezeichnung' => EXTFCSVColumnType::String,
        self::COLUMN_EU_COUNTRY_CODE => EXTFCSVColumnType::String,
        self::COLUMN_EU_VAT_ID => EXTFCSVColumnType::String,
        self::COLUMN_SALUTATION => EXTFCSVColumnType::String,
        'Titel / Akad. Grad' => EXTFCSVColumnType::String,
        'Adelstitel' => EXTFCSVColumnType::String,
        'Namensvorsatz' => EXTFCSVColumnType::String,
        'Adressart' => EXTFCSVColumnType::String,
        self::COLUMN_STREET => EXTFCSVColumnType::String,
        'Postfach' => EXTFCSVColumnType::String,
        self::COLUMN_ZIP_CODE => EXTFCSVColumnType::String,
        self::COLUMN_CITY => EXTFCSVColumnType::String,
        self::COLUMN_COUNTRY => EXTFCSVColumnType::String,
        'Versandzusatz' => EXTFCSVColumnType::String,
        'Adresszusatz' => EXTFCSVColumnType::String,
        'Abweichende Anrede' => EXTFCSVColumnType::String,
        'Abw. Zustellbezeichnung 1' => EXTFCSVColumnType::String,
        'Abw. Zustellbezeichnung2' => EXTFCSVColumnType::String,
        'Kennz. Korrespondenzadresse' => EXTFCSVColumnType::Int,
        'Adresse Gültig von' => EXTFCSVColumnType::FreeString,
        'Adresse Gültig bis' => EXTFCSVColumnType::FreeString,
        self::COLUMN_PHONE_NUMBER => EXTFCSVColumnType::String,
        'Bemerkung (Telefon)' => EXTFCSVColumnType::String,
        'Telefon Geschäftsleitung' => EXTFCSVColumnType::String,
        'Bemerktung (Telefon GL)' => EXTFCSVColumnType::String,
        self::COLUMN_EMAIL => EXTFCSVColumnType::String,
        'Bemerkung (E-Mail)' => EXTFCSVColumnType::String,
        'Internet' => EXTFCSVColumnType::String,
        'Bemerkung (Internet)' => EXTFCSVColumnType::String,
        'Fax' => EXTFCSVColumnType::String,
        'Bemerkung (Fax)' => EXTFCSVColumnType::String,
        'Sonstige' => EXTFCSVColumnType::String,
        'Bemerkung (Sonstige 1)' => EXTFCSVColumnType::String,
        'Bankleitzahl 1' => EXTFCSVColumnType::String,
        'Bankbezeichung 1' => EXTFCSVColumnType::String,
        'Bankkonto-Nummer 1' => EXTFCSVColumnType::String,
        'Länderkennzeichen 1' => EXTFCSVColumnType::String,
        'IBAN-Nr. 1' => EXTFCSVColumnType::String,
        'PICKWARE-DATEV-EMPTY-COLUMN-PLACEHOLDER-1' => EXTFCSVColumnType::String,
        'SWIFT-Code 1' => EXTFCSVColumnType::String,
        'Abw. Kontoinhaber 1' => EXTFCSVColumnType::String,
        'Kennz. Hauptbankverb. 1' => EXTFCSVColumnType::String,
        'Bankverb. 1 Gültig von' => EXTFCSVColumnType::FreeString,
        'Bankverb. 1 Gültig bis' => EXTFCSVColumnType::FreeString,
        'Bankleitzahl 2' => EXTFCSVColumnType::String,
        'Bankbezeichung 2' => EXTFCSVColumnType::String,
        'Bankkonto-Nummer 2' => EXTFCSVColumnType::String,
        'Länderkennzeichen 2' => EXTFCSVColumnType::String,
        'IBAN-Nr. 2' => EXTFCSVColumnType::String,
        'PICKWARE-DATEV-EMPTY-COLUMN-PLACEHOLDER-2' => EXTFCSVColumnType::String,
        'SWIFT-Code 2' => EXTFCSVColumnType::String,
        'Abw. Kontoinhaber 2' => EXTFCSVColumnType::String,
        'Kennz. Hauptbankverb. 2' => EXTFCSVColumnType::String,
        'Bankverb. 2 Gültig von' => EXTFCSVColumnType::FreeString,
        'Bankverb. 2 Gültig bis' => EXTFCSVColumnType::FreeString,
        'Bankleitzahl 3' => EXTFCSVColumnType::String,
        'Bankbezeichung 3' => EXTFCSVColumnType::String,
        'Bankkonto-Nummer 3' => EXTFCSVColumnType::String,
        'Länderkennzeichen 3' => EXTFCSVColumnType::String,
        'IBAN-Nr. 3' => EXTFCSVColumnType::String,
        'PICKWARE-DATEV-EMPTY-COLUMN-PLACEHOLDER-3' => EXTFCSVColumnType::String,
        'SWIFT-Code 3' => EXTFCSVColumnType::String,
        'Abw. Kontoinhaber 3' => EXTFCSVColumnType::String,
        'Kennz. Hauptbankverb. 3' => EXTFCSVColumnType::String,
        'Bankverb. 3 Gültig von' => EXTFCSVColumnType::FreeString,
        'Bankverb. 3 Gültig bis' => EXTFCSVColumnType::FreeString,
        'Bankleitzahl 4' => EXTFCSVColumnType::String,
        'Bankbezeichung 4' => EXTFCSVColumnType::String,
        'Bankkonto-Nummer 4' => EXTFCSVColumnType::String,
        'Länderkennzeichen 4' => EXTFCSVColumnType::String,
        'IBAN-Nr. 4' => EXTFCSVColumnType::String,
        'PICKWARE-DATEV-EMPTY-COLUMN-PLACEHOLDER-4' => EXTFCSVColumnType::String,
        'SWIFT-Code 4' => EXTFCSVColumnType::String,
        'Abw. Kontoinhaber 4' => EXTFCSVColumnType::String,
        'Kennz. Hauptbankverb. 4' => EXTFCSVColumnType::String,
        'Bankverb. 4 Gültig von' => EXTFCSVColumnType::FreeString,
        'Bankverb. 4 Gültig bis' => EXTFCSVColumnType::FreeString,
        'Bankleitzahl 5' => EXTFCSVColumnType::String,
        'Bankbezeichung 5' => EXTFCSVColumnType::String,
        'Bankkonto-Nummer 5' => EXTFCSVColumnType::String,
        'Länderkennzeichen 5' => EXTFCSVColumnType::String,
        'IBAN-Nr. 5' => EXTFCSVColumnType::String,
        'PICKWARE-DATEV-EMPTY-COLUMN-PLACEHOLDER-5' => EXTFCSVColumnType::String,
        'SWIFT-Code 5' => EXTFCSVColumnType::String,
        'Abw. Kontoinhaber 5' => EXTFCSVColumnType::String,
        'Kennz. Hauptbankverb. 5' => EXTFCSVColumnType::String,
        'Bankverb. 5 Gültig von' => EXTFCSVColumnType::FreeString,
        'Bankverb. 5 Gültig bis' => EXTFCSVColumnType::FreeString,
        'PICKWARE-DATEV-EMPTY-COLUMN-PLACEHOLDER-6' => EXTFCSVColumnType::String,
        'Briefanrede' => EXTFCSVColumnType::String,
        'Grußformel' => EXTFCSVColumnType::String,
        self::COLUMN_CUSTOMER_NUMBER => EXTFCSVColumnType::String,
        'Steuernummer' => EXTFCSVColumnType::String,
        'Sprache' => EXTFCSVColumnType::Int,
        'Ansprechpartner' => EXTFCSVColumnType::String,
        'Vertreter' => EXTFCSVColumnType::String,
        'Sachbearbeiter' => EXTFCSVColumnType::String,
        'Diverse-Konto' => EXTFCSVColumnType::Boolean,
        'Ausgabeziel' => EXTFCSVColumnType::Int,
        'Währungssteuerung' => EXTFCSVColumnType::Int,
        'Kreditlimit (Debitor)' => EXTFCSVColumnType::FreeString,
        'Zahlungsbedingung' => EXTFCSVColumnType::Int,
        'Fälligkeit in Tagen (Debitor)' => EXTFCSVColumnType::Int,
        'Skonto in Prozent (Debitor)' => EXTFCSVColumnType::Float,
        'Kreditoren-Ziel 1 (Tage)' => EXTFCSVColumnType::Int,
        'Kreditoren-Skonto 1 (%)' => EXTFCSVColumnType::Float,
        'Kreditoren-Ziel 2 (Tage)' => EXTFCSVColumnType::Int,
        'Kreditoren-Skonto 2 (%)' => EXTFCSVColumnType::Float,
        'Kreditoren-Ziel 3 Brutto (Tage)' => EXTFCSVColumnType::Int,
        'Kreditoren-Ziel 4 (Tage)' => EXTFCSVColumnType::Int,
        'Kreditoren-Skonto 4 (%)' => EXTFCSVColumnType::Float,
        'Kreditoren-Ziel 5 (Tage)' => EXTFCSVColumnType::Int,
        'Kreditoren-Skonto 5 (%)' => EXTFCSVColumnType::Float,
        'Mahnung' => EXTFCSVColumnType::Int,
        'Kontoauszug' => EXTFCSVColumnType::Int,
        'Mahntext 1' => EXTFCSVColumnType::Int,
        'Mahntext 2' => EXTFCSVColumnType::Int,
        'Mahntext 3' => EXTFCSVColumnType::Int,
        'Kontoauszugstest' => EXTFCSVColumnType::Int,
        'Mahnlimit Betrag' => EXTFCSVColumnType::Float,
        'Mahnlimit %' => EXTFCSVColumnType::Float,
        'Zinsberechnung' => EXTFCSVColumnType::Int,
        'Mahnzinssatz 1' => EXTFCSVColumnType::Float,
        'Mahnzinssatz 2' => EXTFCSVColumnType::Float,
        'Mahnzinssatz 3' => EXTFCSVColumnType::Float,
        'Lastschrift' => EXTFCSVColumnType::String,
        'PICKWARE-DATEV-EMPTY-COLUMN-PLACEHOLDER-7' => EXTFCSVColumnType::String,
        'Mandantenbank' => EXTFCSVColumnType::Int,
        'Zahlungsträger' => EXTFCSVColumnType::String,
        'Indiv. Feld 1' => EXTFCSVColumnType::String,
        'Indiv. Feld 2' => EXTFCSVColumnType::String,
        'Indiv. Feld 3' => EXTFCSVColumnType::String,
        'Indiv. Feld 4' => EXTFCSVColumnType::String,
        'Indiv. Feld 5' => EXTFCSVColumnType::String,
        'Indiv. Feld 6' => EXTFCSVColumnType::String,
        'Indiv. Feld 7' => EXTFCSVColumnType::String,
        'Indiv. Feld 8' => EXTFCSVColumnType::String,
        'Indiv. Feld 9' => EXTFCSVColumnType::String,
        'Indiv. Feld 10' => EXTFCSVColumnType::String,
        'Indiv. Feld 11' => EXTFCSVColumnType::String,
        'Indiv. Feld 12' => EXTFCSVColumnType::String,
        'Indiv. Feld 13' => EXTFCSVColumnType::String,
        'Indiv. Feld 14' => EXTFCSVColumnType::String,
        'Indiv. Feld 15' => EXTFCSVColumnType::String,
        'Abweichende Anrede (Rechnungsadresse)' => EXTFCSVColumnType::String,
        'Adressart (Rechnungsadresse)' => EXTFCSVColumnType::String,
        'Straße (Rechnungsadresse)' => EXTFCSVColumnType::String,
        'Postfach (Rechnungsadresse)' => EXTFCSVColumnType::String,
        'Postleitzahl (Rechnungsadresse)' => EXTFCSVColumnType::String,
        'Ort (Rechnungsadresse)' => EXTFCSVColumnType::String,
        'Land (Rechnungsadresse)' => EXTFCSVColumnType::String,
        'Versandzusatz (Rechnungsadresse)' => EXTFCSVColumnType::String,
        'Adresszusatz (Rechnungsadresse)' => EXTFCSVColumnType::String,
        'Abw. Zustellbezeichung 1 (Rechnungsadresse)' => EXTFCSVColumnType::String,
        'Abw. Zustellbezeichung 2 (Rechnungsadresse)' => EXTFCSVColumnType::String,
        'Adresse Gültig von (Rechnungsadresse)' => EXTFCSVColumnType::FreeString,
        'Adresse Gültig bis (Rechnungsadresse)' => EXTFCSVColumnType::FreeString,
        'Bankleitzahl 6' => EXTFCSVColumnType::String,
        'Bankbezeichung 6' => EXTFCSVColumnType::String,
        'Bankkonto-Nummer 6' => EXTFCSVColumnType::String,
        'Länderkennzeichen 6' => EXTFCSVColumnType::String,
        'IBAN-Nr. 6' => EXTFCSVColumnType::String,
        'PICKWARE-DATEV-EMPTY-COLUMN-PLACEHOLDER-8' => EXTFCSVColumnType::String,
        'SWIFT-Code 6' => EXTFCSVColumnType::String,
        'Abw. Kontoinhaber 6' => EXTFCSVColumnType::String,
        'Kennz. Hauptbankverb. 6' => EXTFCSVColumnType::String,
        'Bankverb. 6 Gültig von' => EXTFCSVColumnType::FreeString,
        'Bankverb. 6 Gültig bis' => EXTFCSVColumnType::FreeString,
        'Bankleitzahl 7' => EXTFCSVColumnType::String,
        'Bankbezeichung 7' => EXTFCSVColumnType::String,
        'Bankkonto-Nummer 7' => EXTFCSVColumnType::String,
        'Länderkennzeichen 7' => EXTFCSVColumnType::String,
        'IBAN-Nr. 7' => EXTFCSVColumnType::String,
        'PICKWARE-DATEV-EMPTY-COLUMN-PLACEHOLDER-9' => EXTFCSVColumnType::String,
        'SWIFT-Code 7' => EXTFCSVColumnType::String,
        'Abw. Kontoinhaber 7' => EXTFCSVColumnType::String,
        'Kennz. Hauptbankverb. 7' => EXTFCSVColumnType::String,
        'Bankverb. 7 Gültig von' => EXTFCSVColumnType::FreeString,
        'Bankverb. 7 Gültig bis' => EXTFCSVColumnType::FreeString,
        'Bankleitzahl 8' => EXTFCSVColumnType::String,
        'Bankbezeichung 8' => EXTFCSVColumnType::String,
        'Bankkonto-Nummer 8' => EXTFCSVColumnType::String,
        'Länderkennzeichen 8' => EXTFCSVColumnType::String,
        'IBAN-Nr. 8' => EXTFCSVColumnType::String,
        'PICKWARE-DATEV-EMPTY-COLUMN-PLACEHOLDER-10' => EXTFCSVColumnType::String,
        'SWIFT-Code 8' => EXTFCSVColumnType::String,
        'Abw. Kontoinhaber 8' => EXTFCSVColumnType::String,
        'Kennz. Hauptbankverb. 8' => EXTFCSVColumnType::String,
        'Bankverb. 8 Gültig von' => EXTFCSVColumnType::FreeString,
        'Bankverb. 8 Gültig bis' => EXTFCSVColumnType::FreeString,
        'Bankleitzahl 9' => EXTFCSVColumnType::String,
        'Bankbezeichung 9' => EXTFCSVColumnType::String,
        'Bankkonto-Nummer 9' => EXTFCSVColumnType::String,
        'Länderkennzeichen 9' => EXTFCSVColumnType::String,
        'IBAN-Nr. 9' => EXTFCSVColumnType::String,
        'PICKWARE-DATEV-EMPTY-COLUMN-PLACEHOLDER-11' => EXTFCSVColumnType::String,
        'SWIFT-Code 9' => EXTFCSVColumnType::String,
        'Abw. Kontoinhaber 9' => EXTFCSVColumnType::String,
        'Kennz. Hauptbankverb. 9' => EXTFCSVColumnType::String,
        'Bankverb. 9 Gültig von' => EXTFCSVColumnType::FreeString,
        'Bankverb. 9 Gültig bis' => EXTFCSVColumnType::FreeString,
        'Bankleitzahl 10' => EXTFCSVColumnType::String,
        'Bankbezeichung 10' => EXTFCSVColumnType::String,
        'Bankkonto-Nummer 10' => EXTFCSVColumnType::String,
        'Länderkennzeichen 10' => EXTFCSVColumnType::String,
        'IBAN-Nr. 10' => EXTFCSVColumnType::String,
        'PICKWARE-DATEV-EMPTY-COLUMN-PLACEHOLDER-12' => EXTFCSVColumnType::String,
        'SWIFT-Code 10' => EXTFCSVColumnType::String,
        'Abw. Kontoinhaber 10' => EXTFCSVColumnType::String,
        'Kennz. Hauptbankverb. 10' => EXTFCSVColumnType::String,
        'Bankverb. 10 Gültig von' => EXTFCSVColumnType::FreeString,
        'Bankverb. 10 Gültig bis' => EXTFCSVColumnType::FreeString,
        'Nummer Fremdsystem' => EXTFCSVColumnType::String,
        'Insolvent' => EXTFCSVColumnType::Boolean,
        'SEPA-Mandatsreferenz 1' => EXTFCSVColumnType::String,
        'SEPA-Mandatsreferenz 2' => EXTFCSVColumnType::String,
        'SEPA-Mandatsreferenz 3' => EXTFCSVColumnType::String,
        'SEPA-Mandatsreferenz 4' => EXTFCSVColumnType::String,
        'SEPA-Mandatsreferenz 5' => EXTFCSVColumnType::String,
        'SEPA-Mandatsreferenz 6' => EXTFCSVColumnType::String,
        'SEPA-Mandatsreferenz 7' => EXTFCSVColumnType::String,
        'SEPA-Mandatsreferenz 8' => EXTFCSVColumnType::String,
        'SEPA-Mandatsreferenz 9' => EXTFCSVColumnType::String,
        'SEPA-Mandatsreferenz 10' => EXTFCSVColumnType::String,
        'Verknüpftes OPOS-Konto' => EXTFCSVColumnType::Int,
        'Mahnsperre bis' => EXTFCSVColumnType::FreeString,
        'Lastschriftsperre bis' => EXTFCSVColumnType::FreeString,
        'Zahlungssperre bis' => EXTFCSVColumnType::FreeString,
        'Gebührenberechnung' => EXTFCSVColumnType::Boolean,
        'Mahngebühr 1' => EXTFCSVColumnType::Float,
        'Mahngebühr 2' => EXTFCSVColumnType::Float,
        'Mahngebühr 3' => EXTFCSVColumnType::Float,
        'Pauschalenberechnung' => EXTFCSVColumnType::Boolean,
        'Verzugspauschale 1' => EXTFCSVColumnType::Float,
        'Verzugspauschale 2' => EXTFCSVColumnType::Float,
        'Verzugspauschale 3' => EXTFCSVColumnType::Float,
        'Alternativer Suchname' => EXTFCSVColumnType::String,
        'Status' => EXTFCSVColumnType::Boolean,
        'Anschrift manuell geändert (Korrespondenzadresse)' => EXTFCSVColumnType::Boolean,
        'Anschrift individuell (Korrespondenzadresse)' => EXTFCSVColumnType::String,
        'Anschrift manuell geändert (Rechnungsadresse)' => EXTFCSVColumnType::Boolean,
        'Anschrift individuell (Rechnungsadresse)' => EXTFCSVColumnType::String,
        'Fristberechnung bei Debitor' => EXTFCSVColumnType::Boolean,
        'Mahnfrist 1' => EXTFCSVColumnType::Int,
        'Mahnfrist 2' => EXTFCSVColumnType::Int,
        'Mahnfrist 3' => EXTFCSVColumnType::Int,
        'Letzte Frist' => EXTFCSVColumnType::Int,
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
        private readonly ConfigService $configService,
        private readonly EXTFCSVService $extfCsvService,
    ) {}

    public function getFileName(DateTimeInterface $exportCreatedAt, string $documentExportId, Context $context): string
    {
        /** @var ImportExportEntity $documentExport */
        $documentExport = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $documentExportId, $context);

        $salesChannelId = $documentExport->getConfig()['sales-channel-id'];

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->entityManager->getByPrimaryKey(SalesChannelDefinition::class, $salesChannelId, $context);

        return $this->extfCsvService->getEXTFCSVFileName(
            EXTFCSVExportFormat::BaseData,
            $exportCreatedAt,
            $salesChannel->getName(),
        ) . EXTFCSVService::FILE_EXTENSION;
    }

    public function getFileContent(DateTimeInterface $exportCreatedAt, string $documentExportId, Context $context): string
    {
        $exportedAccountsBaseData = $this->connection->fetchAllAssociative(
            <<<SQL
                SELECT
                    `account_information`.`account` AS `account`,
                    `customer`.`company` AS `company`,
                    `customer`.`last_name` AS `lastName`,
                    `customer`.`first_name` AS `firstName`,
                    `default_billing_address`.`street` AS `street`,
                    `default_billing_address`.`zipCode` AS `zipCode`,
                    `default_billing_address`.`city` AS `city`,
                    `country`.`iso` AS `isoCode`,
                    `default_billing_address`.`phone_number` AS `phoneNumber`,
                    `salutation`.`salutation_key` AS `salutationKey`,
                    `customer`.`email` AS `email`,
                    `customer`.`account_type` AS `accountType`,
                    `customer`.`vat_ids` AS `vatIds`
                FROM
                    `pickware_datev_individual_debtor_account_information` AS `account_information`
                LEFT JOIN `customer`
                    ON `customer`.`id` = `account_information`.`customer_id`
                LEFT JOIN `salutation`
                    ON `salutation`.`id` = `customer`.`salutation_id`
                LEFT JOIN `customer_address` AS `default_billing_address`
                    ON `default_billing_address`.`id` = `customer`.`default_billing_address_id`
                LEFT JOIN `country`
                    ON `country`.`id` = `default_billing_address`.`country_id`
                WHERE `import_export_id` = :documentExportId;
                SQL,
            ['documentExportId' => hex2bin($documentExportId)],
        );

        $rows = array_map(
            function(array $baseData) {
                $accountType = $baseData['accountType'] === CustomerEntity::ACCOUNT_TYPE_PRIVATE ? '1' : '2';
                $salutation = match ($accountType) {
                    '1' => match ($baseData['salutationKey']) {
                        'mr' => 'Herr',
                        'mrs' => 'Frau',
                        default => null,
                    },
                    '2' => 'Firma',
                };

                $vatId = null;
                if (isset($baseData['vatIds'])) {
                    $vatIds = Json::decodeToArray($baseData['vatIds']);
                    $vatId = count($vatIds) > 0 ? $vatIds[0] : null;
                }

                return [
                    self::COLUMN_ACCOUNT => $baseData['account'],
                    self::COLUMN_NAME_OF_COMPANY => $baseData['company'] ?? '',
                    self::COLUMN_LAST_NAME_OF_PERSON => $baseData['lastName'] ?? '',
                    self::COLUMN_FIRST_NAME_OF_PERSON => $baseData['firstName'] ?? '',
                    self::COLUMN_ACCOUNT_TYPE => $accountType,
                    self::COLUMN_EU_COUNTRY_CODE => $vatId !== null ? mb_substr($vatId, 0, 2) : '',
                    self::COLUMN_EU_VAT_ID => $vatId !== null ? mb_substr($vatId, 2) : '',
                    self::COLUMN_SALUTATION => $salutation ?? '',
                    self::COLUMN_STREET => $baseData['street'] ?? '',
                    self::COLUMN_ZIP_CODE => $baseData['zipCode'] ?? '',
                    self::COLUMN_CITY => $baseData['city'] ?? '',
                    self::COLUMN_COUNTRY => $baseData['isoCode'] ?? '',
                    self::COLUMN_PHONE_NUMBER => $baseData['phoneNumber'] ?? '',
                    self::COLUMN_EMAIL => $baseData['email'] ?? '',
                    self::COLUMN_CUSTOMER_NUMBER => $baseData['account'],
                ];
            },
            $exportedAccountsBaseData,
        );

        /** @var ImportExportEntity $documentExport */
        $documentExport = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $documentExportId, $context);
        $documentExportConfig = EntryBatchExportConfig::fromExportConfig($documentExport->getConfig());
        $datevConfig = $this->configService->getConfig($documentExportConfig->salesChannelId, $context)->getValues();

        return implode(
            PHP_EOL,
            [
                implode(
                    ';',
                    $this->extfCsvService->getEXTFCSVFileHeader(
                        EXTFCSVExportFormat::BaseData,
                        $exportCreatedAt,
                        $datevConfig,
                        $documentExportConfig->startDate,
                        $documentExportConfig->endDate,
                    ),
                ),
                implode(';', array_map(
                    fn(string $columnLabel) => str_starts_with($columnLabel, 'PICKWARE-DATEV-EMPTY-COLUMN-PLACEHOLDER') ? self::EMPTY_COLUMN_LABEL : $columnLabel,
                    array_keys(self::COLUMN_LABEL_TYPE_MAPPING),
                )),
                $this->formatRows($rows),
            ],
        );
    }

    private function formatRows(array $rows): string
    {
        $templateString = $this->getRowTemplateString();
        $formattedRows = [];
        foreach ($rows as $row) {
            $formattedRow = [];
            foreach ($row as $columnLabel => $columnValue) {
                $formattedRow[] = $this->extfCsvService->encodeDatevType(
                    self::COLUMN_LABEL_TYPE_MAPPING[$columnLabel] ?? EXTFCSVColumnType::FreeString,
                    $columnValue,
                );
            }
            // avoid iterating over all columns for every row
            $formattedRows[] = vsprintf($templateString, $formattedRow);
        }

        return implode(PHP_EOL, $formattedRows);
    }

    private function getRowTemplateString(): string
    {
        // map columns to empty string except for the export columns
        return implode(';', array_map(
            function(string $columnLabel, EXTFCSVColumnType $columnType) {
                if (in_array($columnLabel, self::EXPORTED_COLUMNS, true)) {
                    return '%s';
                }

                if ($columnType === EXTFCSVColumnType::String) {
                    return '""';
                }

                return '';
            },
            array_keys(self::COLUMN_LABEL_TYPE_MAPPING),
            array_values(self::COLUMN_LABEL_TYPE_MAPPING),
        ));
    }
}
