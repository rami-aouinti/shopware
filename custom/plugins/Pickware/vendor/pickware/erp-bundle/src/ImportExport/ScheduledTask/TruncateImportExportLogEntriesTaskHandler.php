<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\ImportExport\ScheduledTask;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: TruncateImportExportLogEntriesTask::class)]
class TruncateImportExportLogEntriesTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        private readonly Connection $connection,
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly FeatureFlagService $featureFlagService,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $this->connection->transactional(function(): void {
            $importExportIds = $this->connection->fetchFirstColumn(
                <<<SQL
                    SELECT HEX(`importExport`.`id`)
                    FROM `pickware_erp_import_export` as `importExport`
                    LEFT JOIN `pickware_erp_import_export_profile` AS `importExportProfile`
                        ON `importExport`.`profile_technical_name` = `importExportProfile`.`technical_name`
                    WHERE
                        `importExportProfile`.`log_retention_days` IS NOT NULL
                        AND `importExport`.`created_at` < DATE_SUB(UTC_TIMESTAMP(3), INTERVAL `importExportProfile`.`log_retention_days` DAY)
                        AND `importExport`.`logs_truncated` = 0
                        AND EXISTS (
                            SELECT 1
                            FROM `pickware_erp_import_export_log_entry` AS `importExportLogEntry`
                            WHERE `importExportLogEntry`.`import_export_id` = `importExport`.`id`
                        );
                    SQL
            );
            if (count($importExportIds) === 0) {
                return;
            }

            // Delete log entries
            $affectedRows = $this->connection->executeStatement(
                <<<SQL
                    DELETE `importExportLogEntry`
                    FROM `pickware_erp_import_export_log_entry` AS `importExportLogEntry`
                    WHERE `importExportLogEntry`.`import_export_id` IN (:importExportIds)
                    SQL,
                ['importExportIds' => array_map(hex2bin(...), $importExportIds)],
                ['importExportIds' => ArrayParameterType::BINARY],
            );
            if (!$affectedRows) {
                return;
            }

            // Mark import export as truncated
            $this->connection->executeStatement(
                <<<SQL
                    UPDATE `pickware_erp_import_export`
                    SET `logs_truncated` = 1
                    WHERE `id` IN (:importExportIds)
                    SQL,
                ['importExportIds' => array_map(hex2bin(...), $importExportIds)],
                ['importExportIds' => ArrayParameterType::BINARY],
            );
        });
    }
}
