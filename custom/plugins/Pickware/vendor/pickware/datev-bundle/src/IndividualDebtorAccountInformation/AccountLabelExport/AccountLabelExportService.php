<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\IndividualDebtorAccountInformation\AccountLabelExport;

use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\Config\ConfigService;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchExportConfig;
use Pickware\DatevBundle\GenericEXTFCSVExport\EXTFCSVColumnType;
use Pickware\DatevBundle\GenericEXTFCSVExport\EXTFCSVExportFormat;
use Pickware\DatevBundle\GenericEXTFCSVExport\EXTFCSVService;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class AccountLabelExportService
{
    public const COLUMN_ACCOUNT = 'Konto';
    public const COLUMN_ACCOUNT_NAME = 'Kontenbeschriftung';
    public const COLUMN_LANGUAGE_ID = 'Sprach-ID';
    public const COLUMN_ACCOUNT_NAME_LONG = 'Kontenbeschriftung lang';
    public const COLUMN_LABEL_TYPE_MAPPING = [
        self::COLUMN_ACCOUNT => EXTFCSVColumnType::Int,
        self::COLUMN_ACCOUNT_NAME => EXTFCSVColumnType::String,
        self::COLUMN_LANGUAGE_ID => EXTFCSVColumnType::String,
        self::COLUMN_ACCOUNT_NAME_LONG => EXTFCSVColumnType::String,
    ];
    private const GERMAN_LANGUAGE_ID = 'de-DE';

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
            EXTFCSVExportFormat::AccountLabel,
            $exportCreatedAt,
            $salesChannel->getName(),
        ) . EXTFCSVService::FILE_EXTENSION;
    }

    public function getFileContent(DateTimeInterface $exportCreatedAt, string $documentExportId, Context $context): string
    {
        $accountsInDocumentExport = array_filter($this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT
                    `account`
                FROM
                    `pickware_datev_individual_debtor_account_information`
                WHERE `import_export_id` = :documentExportId
                SQL,
            ['documentExportId' => hex2bin($documentExportId)],
        ));

        $rows = array_map(
            fn(int $account) => [
                self::COLUMN_ACCOUNT => $account,
                self::COLUMN_ACCOUNT_NAME => sprintf(
                    'Kundenkonto %s',
                    $account,
                ),
                self::COLUMN_LANGUAGE_ID => self::GERMAN_LANGUAGE_ID,
                self::COLUMN_ACCOUNT_NAME_LONG => '',
            ],
            $accountsInDocumentExport,
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
                        EXTFCSVExportFormat::AccountLabel,
                        $exportCreatedAt,
                        $datevConfig,
                        $documentExportConfig->startDate,
                        $documentExportConfig->endDate,
                    ),
                ),
                implode(';', array_keys(self::COLUMN_LABEL_TYPE_MAPPING)),
                $this->formatRows($rows),
            ],
        );
    }

    private function formatRows(array $rows): string
    {
        $formattedRows = [];
        foreach ($rows as $row) {
            $formattedRow = [];
            foreach ($row as $columnLabel => $columnValue) {
                $formattedRow[] = $this->extfCsvService->encodeDatevType(
                    self::COLUMN_LABEL_TYPE_MAPPING[$columnLabel] ?? EXTFCSVColumnType::FreeString,
                    $columnValue,
                );
            }
            $formattedRows[] = implode(';', $formattedRow);
        }

        return implode(PHP_EOL, $formattedRows);
    }
}
