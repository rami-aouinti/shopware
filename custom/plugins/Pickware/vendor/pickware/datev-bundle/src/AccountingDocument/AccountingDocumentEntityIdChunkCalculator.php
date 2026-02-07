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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\DatevBundle\EntryBatch\DependencyInjection\EntityIdChunkCalculatorRegistry;
use Pickware\DatevBundle\EntryBatch\EntityIdChunkCalculator;
use Pickware\DatevBundle\EntryBatch\EntityIdCount;
use Pickware\DatevBundle\EntryBatch\ImportExportProfile\EntryBatchExportConfig;
use Pickware\DatevBundle\EntryBatch\SingleEntityIdCount;
use Pickware\DatevBundle\PickwareDatevBundle;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportDefinition;
use Pickware\PickwareErpStarter\ImportExport\Model\ImportExportEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: EntityIdChunkCalculatorRegistry::DI_CONTAINER_TAG)]
class AccountingDocumentEntityIdChunkCalculator implements EntityIdChunkCalculator
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Connection $connection,
    ) {}

    public function getEntityIdCountForExportConfig(array $exportConfig, Context $context): EntityIdCount
    {
        $entryBatchConfig = EntryBatchExportConfig::fromExportConfig($exportConfig);
        $accountingDocumentConfig = AccountingDocumentExportConfig::fromExportConfig($exportConfig);

        $timeZone = $this->connection->fetchOne('SELECT @@session.time_zone');
        $this->connection->executeStatement('SET @@session.time_zone = "+00:00"');
        $countResult = $this->connection->fetchAssociative(
            'SELECT COUNT(d.`id`) as `count` FROM `document` d
            LEFT JOIN `document_type` dt ON d.`document_type_id` = dt.`id`
            LEFT JOIN `order` o ON d.`order_id` = o.`id` AND d.`order_version_id` = o.`version_id`
            LEFT JOIN `sales_channel` sc ON o.`sales_channel_id` = sc.`id`
            WHERE dt.`technical_name` in (:documentTypes)
                AND NOT sc.`type_id` = :posSalesChannelTypeId
                AND sc.`id` = :salesChannelId
                AND (
                    (
                        # It is possible for the `documentDate` to be missing from the document config, either by being `null` or not existing at all.
                        # If this is the case, we fall back to the `created_at` field when checking if the document is within the search range.
                        (
                            NOT JSON_CONTAINS_PATH(d.`config`, \'all\', \'$.documentDate\') OR
                            JSON_TYPE(JSON_EXTRACT(d.`config`, \'$.documentDate\')) = \'NULL\'
                        )
                        # DAL written fields are already in the required format
                        AND d.`created_at` >= :startDate
                        AND d.`created_at` <= :endDate
                    ) OR (
                        # Documents created via e.g. the admin panel might have a document date formatted differently, e.g. as "%Y-%m-%d %H:%i:%s"
                        CAST(JSON_UNQUOTE(JSON_EXTRACT(d.`config`, \'$.documentDate\')) as DATETIME(3)) >= :startDate
                        AND CAST(JSON_UNQUOTE(JSON_EXTRACT(d.`config`, \'$.documentDate\')) as DATETIME(3)) <= :endDate
                    )
                );',
            [
                'startDate' => $entryBatchConfig->startDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'endDate' => $entryBatchConfig->endDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'documentTypes' => $accountingDocumentConfig->documentTypes,
                'posSalesChannelTypeId' => hex2bin(PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID),
                'salesChannelId' => hex2bin($entryBatchConfig->salesChannelId),
            ],
            ['documentTypes' => ArrayParameterType::STRING],
        );
        $this->connection->executeStatement(sprintf('SET @@session.time_zone = "%s"', $timeZone));

        return new SingleEntityIdCount((int) $countResult['count']);
    }

    public function getEntityIdChunkForExport(string $exportId, int $chunkSize, int $offset, Context $context): array
    {
        /** @var ImportExportEntity $export */
        $export = $this->entityManager->getByPrimaryKey(ImportExportDefinition::class, $exportId, $context);
        $entryBatchConfig = EntryBatchExportConfig::fromExportConfig($export->getConfig());
        $accountingDocumentConfig = AccountingDocumentExportConfig::fromExportConfig($export->getConfig());

        $timeZone = $this->connection->fetchOne('SELECT @@session.time_zone');
        $this->connection->executeStatement('SET @@session.time_zone = "+00:00"');
        $documentIdResult = $this->connection->fetchAllAssociative(
            'SELECT
                d.`id` as `documentId`,
                CAST(JSON_UNQUOTE(JSON_EXTRACT(d.`config`, \'$.documentDate\')) as DATETIME(3)) as `documentDate`
            FROM `document` d
                LEFT JOIN `document_type` dt ON d.`document_type_id` = dt.`id`
                LEFT JOIN `order` o ON d.`order_id` = o.`id` AND d.`order_version_id` = o.`version_id`
                LEFT JOIN `sales_channel` sc ON o.`sales_channel_id` = sc.`id`
            WHERE dt.`technical_name` in (:documentTypes)
                AND NOT sc.`type_id` = :posSalesChannelTypeId
                AND sc.`id` = :salesChannelId
                AND (
                    (
                        # It is possible for the `documentDate` to be missing from the document config, either by being `null` or not existing at all.
                        # If this is the case, we fall back to the `created_at` field when checking if the document is within the search range.
                        (
                            NOT JSON_CONTAINS_PATH(d.`config`, \'all\', \'$.documentDate\') OR
                            JSON_TYPE(JSON_EXTRACT(d.`config`, \'$.documentDate\')) = \'NULL\'
                        )
                        # DAL written fields are already in the required format
                        AND d.`created_at` >= :startDate
                        AND d.`created_at` <= :endDate
                    ) OR (
                        # Documents created via e.g. the admin panel might have a document date formatted differently, e.g. as "%Y-%m-%d %H:%i:%s"
                        CAST(JSON_UNQUOTE(JSON_EXTRACT(d.`config`, \'$.documentDate\')) as DATETIME(3)) >= :startDate
                        AND CAST(JSON_UNQUOTE(JSON_EXTRACT(d.`config`, \'$.documentDate\')) as DATETIME(3)) <= :endDate
                    )
                )
                AND d.`created_at` <= :exportDate
            ORDER BY `documentDate`, d.`id`
            LIMIT ' . $chunkSize . '
            OFFSET ' . $offset,
            [
                'startDate' => $entryBatchConfig->startDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'endDate' => $entryBatchConfig->endDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'exportDate' => $export->getCreatedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'documentTypes' => $accountingDocumentConfig->documentTypes,
                'posSalesChannelTypeId' => hex2bin(PickwareDatevBundle::PICKWARE_POS_SALES_CHANNEL_TYPE_ID),
                'salesChannelId' => hex2bin($entryBatchConfig->salesChannelId),
            ],
            ['documentTypes' => ArrayParameterType::STRING],
        );
        $this->connection->executeStatement(sprintf('SET @@session.time_zone = "%s"', $timeZone));

        return array_map(fn($row) => bin2hex($row['documentId']), $documentIdResult);
    }

    public function getEntryBatchRecordCreatorTechnicalName(): string
    {
        return AccountingDocumentRecordCreator::TECHNICAL_NAME;
    }
}
