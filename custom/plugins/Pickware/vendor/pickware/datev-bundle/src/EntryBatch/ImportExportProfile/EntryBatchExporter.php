<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\EntryBatch\ImportExportProfile;

use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\Config\ConfigService;
use Pickware\DatevBundle\EntryBatch\DependencyInjection\EntityIdChunkCalculatorRegistry;
use Pickware\DatevBundle\EntryBatch\DependencyInjection\EntryBatchRecordCreatorRegistry;
use Pickware\DatevBundle\EntryBatch\EntryBatchLogMessage;
use Pickware\DatevBundle\EntryBatch\EntryBatchRecordCollection;
use Pickware\DatevBundle\GenericEXTFCSVExport\EXTFCSVColumnType;
use Pickware\DatevBundle\GenericEXTFCSVExport\EXTFCSVExportFormat;
use Pickware\DatevBundle\GenericEXTFCSVExport\EXTFCSVService;
use Pickware\HttpUtils\JsonApi\JsonApiErrors;
use Pickware\PickwareErpStarter\ImportExport\Exporter;
use Pickware\PickwareErpStarter\ImportExport\FileExporter;
use Pickware\PickwareErpStarter\ImportExport\HeaderExporter;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportElementDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportLogEntryDefinition;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag(name: 'pickware_erp.import_export.exporter', attributes: ['profileTechnicalName' => 'entry-batch'])]
class EntryBatchExporter implements Exporter, FileExporter, HeaderExporter
{
    public const TECHNICAL_NAME = 'entry-batch';
    public const COLUMN_REVENUE = 'Umsatz (ohne Soll/Haben-Kz)';
    public const COLUMN_DEBIT_CREDIT_IDENTIFIER = 'Soll/Haben-Kennzeichen';
    public const COLUMN_ACCOUNT = 'Konto';
    public const COLUMN_CONTRA_ACCOUNT = 'Gegenkonto (ohne BU-Schlüssel)';
    public const COLUMN_DOCUMENT_DATE = 'Belegdatum';
    public const COLUMN_DOCUMENT_FIELD_1 = 'Belegfeld 1';
    public const COLUMN_POSTING_TEXT = 'Buchungstext';
    public const COLUMN_DOCUMENT_INFO_TYPE_1 = 'Beleginfo - Art 1';
    public const COLUMN_DOCUMENT_INFO_CONTENT_1 = 'Beleginfo - Inhalt 1';
    public const COLUMN_DOCUMENT_INFO_TYPE_2 = 'Beleginfo - Art 2';
    public const COLUMN_DOCUMENT_INFO_CONTENT_2 = 'Beleginfo - Inhalt 2';
    public const COLUMN_DOCUMENT_INFO_TYPE_3 = 'Beleginfo - Art 3';
    public const COLUMN_DOCUMENT_INFO_CONTENT_3 = 'Beleginfo - Inhalt 3';
    public const COLUMN_DOCUMENT_INFO_TYPE_4 = 'Beleginfo - Art 4';
    public const COLUMN_DOCUMENT_INFO_CONTENT_4 = 'Beleginfo - Inhalt 4';
    public const COLUMN_EU_COUNTRY_AND_VAT_ID = 'EU-Land u. UStID (Bestimmung)';
    public const COLUMN_EU_TAX_RATE = 'EU-Steuersatz (Bestimmung)';
    public const COLUMN_FIXATION = 'Festschreibung';
    public const COLUMN_TASK_NUMBER = 'Auftragsnummer';
    public const COLUMN_RECEIPT_LINK = 'Beleglink';
    public const COLUMN_COST_CENTER_1 = 'KOST1 - Kostenstelle';
    public const COLUMN_COST_CENTER_2 = 'KOST2 - Kostenstelle';
    public const COLUMN_ADDITIONAL_INFORMATION_TYPE_1 = 'Zusatzinformation - Art 1';
    public const COLUMN_ADDITIONAL_INFORMATION_CONTENT_1 = 'Zusatzinformation - Inhalt 1';
    public const COLUMN_ADDITIONAL_INFORMATION_TYPE_2 = 'Zusatzinformation - Art 2';
    public const COLUMN_ADDITIONAL_INFORMATION_CONTENT_2 = 'Zusatzinformation - Inhalt 2';
    public const COLUMN_ADDITIONAL_INFORMATION_TYPE_3 = 'Zusatzinformation - Art 3';
    public const COLUMN_ADDITIONAL_INFORMATION_CONTENT_3 = 'Zusatzinformation - Inhalt 3';
    public const COLUMN_ADDITIONAL_INFORMATION_TYPE_4 = 'Zusatzinformation - Art 4';
    public const COLUMN_ADDITIONAL_INFORMATION_CONTENT_4 = 'Zusatzinformation - Inhalt 4';
    public const COLUMN_LABEL_TYPE_MAPPING = [
        self::COLUMN_REVENUE => EXTFCSVColumnType::Float,
        self::COLUMN_DEBIT_CREDIT_IDENTIFIER => EXTFCSVColumnType::String,
        'WKZ Umsatz' => EXTFCSVColumnType::String,
        'Kurs' => EXTFCSVColumnType::Float,
        'Basis-Umsatz' => EXTFCSVColumnType::Float,
        'WKZ Basis-Umsatz' => EXTFCSVColumnType::String,
        self::COLUMN_ACCOUNT => EXTFCSVColumnType::Int,
        self::COLUMN_CONTRA_ACCOUNT => EXTFCSVColumnType::Int,
        'BU-Schlüssel' => EXTFCSVColumnType::String,
        self::COLUMN_DOCUMENT_DATE => EXTFCSVColumnType::FreeString,
        self::COLUMN_DOCUMENT_FIELD_1 => EXTFCSVColumnType::String,
        'Belegfeld 2' => EXTFCSVColumnType::String,
        'Skonto' => EXTFCSVColumnType::Float,
        self::COLUMN_POSTING_TEXT => EXTFCSVColumnType::String,
        'Postensperre' => EXTFCSVColumnType::Boolean,
        'Diverse Adressnummer' => EXTFCSVColumnType::String,
        'Geschäftspartnerbank' => EXTFCSVColumnType::Int,
        'Sachverhalt' => EXTFCSVColumnType::Int,
        'Zinssperre' => EXTFCSVColumnType::Boolean,
        self::COLUMN_RECEIPT_LINK => EXTFCSVColumnType::String,
        self::COLUMN_DOCUMENT_INFO_TYPE_1 => EXTFCSVColumnType::String,
        self::COLUMN_DOCUMENT_INFO_CONTENT_1 => EXTFCSVColumnType::String,
        self::COLUMN_DOCUMENT_INFO_TYPE_2 => EXTFCSVColumnType::String,
        self::COLUMN_DOCUMENT_INFO_CONTENT_2 => EXTFCSVColumnType::String,
        self::COLUMN_DOCUMENT_INFO_TYPE_3 => EXTFCSVColumnType::String,
        self::COLUMN_DOCUMENT_INFO_CONTENT_3 => EXTFCSVColumnType::String,
        self::COLUMN_DOCUMENT_INFO_TYPE_4 => EXTFCSVColumnType::String,
        self::COLUMN_DOCUMENT_INFO_CONTENT_4 => EXTFCSVColumnType::String,
        'Beleginfo - Art 5' => EXTFCSVColumnType::String,
        'Beleginfo - Inhalt 5' => EXTFCSVColumnType::String,
        'Beleginfo - Art 6' => EXTFCSVColumnType::String,
        'Beleginfo - Inhalt 6' => EXTFCSVColumnType::String,
        'Beleginfo - Art 7' => EXTFCSVColumnType::String,
        'Beleginfo - Inhalt 7' => EXTFCSVColumnType::String,
        'Beleginfo - Art 8' => EXTFCSVColumnType::String,
        'Beleginfo - Inhalt 8' => EXTFCSVColumnType::String,
        self::COLUMN_COST_CENTER_1 => EXTFCSVColumnType::String,
        self::COLUMN_COST_CENTER_2 => EXTFCSVColumnType::String,
        'Kost-Menge' => EXTFCSVColumnType::Float,
        self::COLUMN_EU_COUNTRY_AND_VAT_ID => EXTFCSVColumnType::String,
        self::COLUMN_EU_TAX_RATE => EXTFCSVColumnType::Float,
        'Abw. Versteuerungsart' => EXTFCSVColumnType::String,
        'Sachverhalt L+L' => EXTFCSVColumnType::Int,
        'Funktionsergänzung L+L' => EXTFCSVColumnType::Int,
        'BU 49 Hauptfunktionstyp' => EXTFCSVColumnType::Int,
        'BU 49 Hauptfunktionsnummer' => EXTFCSVColumnType::Int,
        'BU 49 Funktionsergänzung' => EXTFCSVColumnType::Int,
        self::COLUMN_ADDITIONAL_INFORMATION_TYPE_1 => EXTFCSVColumnType::String,
        self::COLUMN_ADDITIONAL_INFORMATION_CONTENT_1 => EXTFCSVColumnType::String,
        self::COLUMN_ADDITIONAL_INFORMATION_TYPE_2 => EXTFCSVColumnType::String,
        self::COLUMN_ADDITIONAL_INFORMATION_CONTENT_2 => EXTFCSVColumnType::String,
        self::COLUMN_ADDITIONAL_INFORMATION_TYPE_3 => EXTFCSVColumnType::String,
        self::COLUMN_ADDITIONAL_INFORMATION_CONTENT_3 => EXTFCSVColumnType::String,
        self::COLUMN_ADDITIONAL_INFORMATION_TYPE_4 => EXTFCSVColumnType::String,
        self::COLUMN_ADDITIONAL_INFORMATION_CONTENT_4 => EXTFCSVColumnType::String,
        'Zusatzinformation - Art 5' => EXTFCSVColumnType::String,
        'Zusatzinformation - Inhalt 5' => EXTFCSVColumnType::String,
        'Zusatzinformation - Art 6' => EXTFCSVColumnType::String,
        'Zusatzinformation - Inhalt 6' => EXTFCSVColumnType::String,
        'Zusatzinformation - Art 7' => EXTFCSVColumnType::String,
        'Zusatzinformation - Inhalt 7' => EXTFCSVColumnType::String,
        'Zusatzinformation - Art 8' => EXTFCSVColumnType::String,
        'Zusatzinformation - Inhalt 8' => EXTFCSVColumnType::String,
        'Zusatzinformation - Art 9' => EXTFCSVColumnType::String,
        'Zusatzinformation - Inhalt 9' => EXTFCSVColumnType::String,
        'Zusatzinformation - Art 10' => EXTFCSVColumnType::String,
        'Zusatzinformation - Inhalt 10' => EXTFCSVColumnType::String,
        'Zusatzinformation - Art 11' => EXTFCSVColumnType::String,
        'Zusatzinformation - Inhalt 11' => EXTFCSVColumnType::String,
        'Zusatzinformation - Art 12' => EXTFCSVColumnType::String,
        'Zusatzinformation - Inhalt 12' => EXTFCSVColumnType::String,
        'Zusatzinformation - Art 13' => EXTFCSVColumnType::String,
        'Zusatzinformation - Inhalt 13' => EXTFCSVColumnType::String,
        'Zusatzinformation - Art 14' => EXTFCSVColumnType::String,
        'Zusatzinformation - Inhalt 14' => EXTFCSVColumnType::String,
        'Zusatzinformation - Art 15' => EXTFCSVColumnType::String,
        'Zusatzinformation - Inhalt 15' => EXTFCSVColumnType::String,
        'Zusatzinformation - Art 16' => EXTFCSVColumnType::String,
        'Zusatzinformation - Inhalt 16' => EXTFCSVColumnType::String,
        'Zusatzinformation - Art 17' => EXTFCSVColumnType::String,
        'Zusatzinformation - Inhalt 17' => EXTFCSVColumnType::String,
        'Zusatzinformation - Art 18' => EXTFCSVColumnType::String,
        'Zusatzinformation - Inhalt 18' => EXTFCSVColumnType::String,
        'Zusatzinformation - Art 19' => EXTFCSVColumnType::String,
        'Zusatzinformation - Inhalt 19' => EXTFCSVColumnType::String,
        'Zusatzinformation - Art 20' => EXTFCSVColumnType::String,
        'Zusatzinformation - Inhalt 20' => EXTFCSVColumnType::String,
        'Stück' => EXTFCSVColumnType::Int,
        'Gewicht' => EXTFCSVColumnType::Float,
        'Zahlweise' => EXTFCSVColumnType::Int,
        'Forderungsart' => EXTFCSVColumnType::String,
        'Veranlagungsjahr' => EXTFCSVColumnType::FreeString,
        'Zugeordnete Fälligkeit' => EXTFCSVColumnType::FreeString,
        'Skontotyp' => EXTFCSVColumnType::Int,
        self::COLUMN_TASK_NUMBER => EXTFCSVColumnType::String,
        'Buchungstyp (Anzahlungen)' => EXTFCSVColumnType::String,
        'Ust-Schlüssel (Anzahlungen)' => EXTFCSVColumnType::Int,
        'EU-Land (Anzahlungen)' => EXTFCSVColumnType::String,
        'Sachverhalt L+L (Anzahlungen)' => EXTFCSVColumnType::Int,
        'EU-Steuersatz (Anzahlungen)' => EXTFCSVColumnType::Float,
        'Erlöskonto (Anzahlungen)' => EXTFCSVColumnType::Int,
        'Herkunft-Kz' => EXTFCSVColumnType::String,
        'Buchungs GUID' => EXTFCSVColumnType::String,
        'KOST-Datum' => EXTFCSVColumnType::FreeString,
        'SEPA-Mandatsreferenz' => EXTFCSVColumnType::String,
        'Skontosperre' => EXTFCSVColumnType::Boolean,
        'Gesellschaftername' => EXTFCSVColumnType::String,
        'Beteiligtennummer' => EXTFCSVColumnType::Int,
        'Identifikationsnummer' => EXTFCSVColumnType::String,
        'Zeichnernummer' => EXTFCSVColumnType::String,
        'Postensperre bis' => EXTFCSVColumnType::FreeString,
        'Bezeichnung SoBil-Sachverhalt' => EXTFCSVColumnType::String,
        'Kennzeichen SoBil-Buchung' => EXTFCSVColumnType::Boolean,
        self::COLUMN_FIXATION => EXTFCSVColumnType::Boolean,
        'Leistungsdatum' => EXTFCSVColumnType::FreeString,
        'Datum Zuord.Steuerperiode' => EXTFCSVColumnType::FreeString,
        'Fälligkeit' => EXTFCSVColumnType::FreeString,
        'Generalumkehr (GU)' => EXTFCSVColumnType::String,
        'Steuersatz' => EXTFCSVColumnType::Float,
        'Land' => EXTFCSVColumnType::String,
        'Abrechnungsreferenz' => EXTFCSVColumnType::String,
        'BVV-Position' => EXTFCSVColumnType::Int,
        'EU-Land u. UStID (Ursprung)' => EXTFCSVColumnType::String,
        'EU-Steuersatz (Ursprung)' => EXTFCSVColumnType::Float,
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly ConfigService $configService,
        private readonly EntityIdChunkCalculatorRegistry $chunkCalculatorRegistry,
        private readonly EntryBatchRecordCreatorRegistry $recordCreatorRegistry,
        private readonly EXTFCSVService $extfCsvService,
        #[Autowire(param: 'pickware_datev.import_export_profiles.entry_batch.batch_size')]
        private readonly int $chunkSize,
    ) {}

    public function exportChunk(string $exportId, int $nextRowNumberToWrite, Context $context): ?int
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $entryBatchExportConfig = EntryBatchExportConfig::fromExportConfig($export->getConfig());
        $recordCreatorTechnicalName = $entryBatchExportConfig->entryBatchRecordCreatorTechnicalName;

        $entityIds = $this->chunkCalculatorRegistry
            ->getEntityIdChunkCalculatorByEntryBatchRecordCreatorTechnicalName($recordCreatorTechnicalName)
            ->getEntityIdChunkForExport($exportId, $this->chunkSize, $nextRowNumberToWrite - 1, $context);

        $entryBatchRecords = $this->recordCreatorRegistry
            ->getEntryBatchRecordCreatorByTechnicalName($recordCreatorTechnicalName)
            ->createEntryBatchRecords($entityIds, $export->getConfig(), $exportId, $context);

        $exportRows = $this->getEntryBatchRecordExportRows($entryBatchRecords);

        $exportElementPayloads = [];
        $successBaseIndex = $entryBatchExportConfig->nextRowNumberToWrite;
        foreach ($exportRows as $index => $exportRow) {
            $exportElementPayloads[] = [
                'id' => Uuid::randomHex(),
                'importExportId' => $exportId,
                'rowNumber' => $successBaseIndex + $index,
                'rowData' => $exportRow,
            ];
        }
        $this->entityManager->create(ImportExportElementDefinition::class, $exportElementPayloads, $context);

        $this->entityManager->create(
            ImportExportLogEntryDefinition::class,
            array_map(
                fn(EntryBatchLogMessage $logMessage) => $logMessage->toImportExportLogEntryPayload($exportId),
                $entryBatchRecords->getLogMessages(),
            ),
            $context,
        );

        $entryBatchExportConfig->nextRowNumberToWrite += count($exportElementPayloads);
        $this->entityManager->update(
            ImportExportDefinition::class,
            [
                [
                    'id' => $exportId,
                    'config' => array_merge(
                        $export->getConfig(),
                        $entryBatchExportConfig->jsonSerialize(),
                    ),
                ],
            ],
            $context,
        );

        $nextRowNumberToWrite += $this->chunkSize;
        if (count($entityIds) < $this->chunkSize) {
            return null;
        }

        return $nextRowNumberToWrite;
    }

    private function getEntryBatchRecordExportRows(EntryBatchRecordCollection $entryBatchRecordCollection): array
    {
        $rows = [];
        foreach ($entryBatchRecordCollection->getEntries() as $entryBatchRecord) {
            $columnValues = [
                self::COLUMN_REVENUE => $entryBatchRecord->revenue,
                self::COLUMN_DEBIT_CREDIT_IDENTIFIER => $entryBatchRecord->debitCreditIdentifier,
                self::COLUMN_ACCOUNT => $entryBatchRecord->account,
                self::COLUMN_CONTRA_ACCOUNT => $entryBatchRecord->contraAccount,
                self::COLUMN_DOCUMENT_DATE => $entryBatchRecord->documentDate->format('dm'),
                self::COLUMN_DOCUMENT_FIELD_1 => $entryBatchRecord->documentField1,
                self::COLUMN_POSTING_TEXT => $entryBatchRecord->postingText,
                self::COLUMN_RECEIPT_LINK => $entryBatchRecord->receiptLink,
                self::COLUMN_DOCUMENT_INFO_TYPE_1 => $entryBatchRecord->documentInfoType1,
                self::COLUMN_DOCUMENT_INFO_CONTENT_1 => $entryBatchRecord->documentInfoContent1,
                self::COLUMN_DOCUMENT_INFO_TYPE_2 => $entryBatchRecord->documentInfoType2,
                self::COLUMN_DOCUMENT_INFO_CONTENT_2 => $entryBatchRecord->documentInfoContent2,
                self::COLUMN_DOCUMENT_INFO_TYPE_3 => $entryBatchRecord->documentInfoType3,
                self::COLUMN_DOCUMENT_INFO_CONTENT_3 => $entryBatchRecord->documentInfoContent3,
                self::COLUMN_DOCUMENT_INFO_TYPE_4 => $entryBatchRecord->documentInfoType4,
                self::COLUMN_DOCUMENT_INFO_CONTENT_4 => $entryBatchRecord->documentInfoContent4,
                self::COLUMN_COST_CENTER_1 => $entryBatchRecord->costCenter1,
                self::COLUMN_COST_CENTER_2 => $entryBatchRecord->costCenter2,
                self::COLUMN_EU_COUNTRY_AND_VAT_ID => $entryBatchRecord->euCountryAndVatId,
                self::COLUMN_EU_TAX_RATE => $entryBatchRecord->euTaxRate,
                self::COLUMN_ADDITIONAL_INFORMATION_TYPE_1 => $entryBatchRecord->additionalInformationType1,
                self::COLUMN_ADDITIONAL_INFORMATION_CONTENT_1 => $entryBatchRecord->additionalInformationContent1,
                self::COLUMN_ADDITIONAL_INFORMATION_TYPE_2 => $entryBatchRecord->additionalInformationType2,
                self::COLUMN_ADDITIONAL_INFORMATION_CONTENT_2 => $entryBatchRecord->additionalInformationContent2,
                self::COLUMN_ADDITIONAL_INFORMATION_TYPE_3 => $entryBatchRecord->additionalInformationType3,
                self::COLUMN_ADDITIONAL_INFORMATION_CONTENT_3 => $entryBatchRecord->additionalInformationContent3,
                self::COLUMN_ADDITIONAL_INFORMATION_TYPE_4 => $entryBatchRecord->additionalInformationType4,
                self::COLUMN_ADDITIONAL_INFORMATION_CONTENT_4 => $entryBatchRecord->additionalInformationContent4,
                self::COLUMN_FIXATION => $entryBatchRecord->fixation,
                self::COLUMN_TASK_NUMBER => $entryBatchRecord->taskNumber,
            ];

            $currentRow = [];
            foreach (array_keys(self::COLUMN_LABEL_TYPE_MAPPING) as $columnLabel) {
                $currentRow[$columnLabel] = $columnValues[$columnLabel] ?? '';
            }
            $rows[] = $currentRow;
        }

        return $rows;
    }

    public function getFileName(string $exportId, Context $context): string
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);

        $salesChannelId = $export->getConfig()['sales-channel-id'];

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->entityManager->getByPrimaryKey(SalesChannelDefinition::class, $salesChannelId, $context);

        return $this->extfCsvService->getEXTFCSVFileName(
            EXTFCSVExportFormat::EntryBatch,
            $export->getCreatedAt(),
            $salesChannel->getName(),
        );
    }

    public function getHeader(string $exportId, Context $context): array
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $exportConfig = EntryBatchExportConfig::fromExportConfig($export->getConfig());
        $datevConfig = $this->configService->getConfig($exportConfig->salesChannelId, $context)->getValues();

        return [
            $this->extfCsvService->getEXTFCSVFileHeader(
                EXTFCSVExportFormat::EntryBatch,
                $export->getCreatedAt(),
                $datevConfig,
                $exportConfig->startDate,
                $exportConfig->endDate,
            ),
            array_keys(self::COLUMN_LABEL_TYPE_MAPPING),
        ];
    }

    public function validateConfig(array $config): JsonApiErrors
    {
        $errors = EntryBatchExportConfig::validate($config);
        if ($errors->count() > 0) {
            return $errors;
        }

        $exportConfig = EntryBatchExportConfig::fromExportConfig($config);

        return $this->recordCreatorRegistry
            ->getEntryBatchRecordCreatorByTechnicalName($exportConfig->entryBatchRecordCreatorTechnicalName)
            ->validateConfig($config);
    }

    public function getEntityDefinitionClassName(): string
    {
        return DocumentDefinition::class;
    }
}
