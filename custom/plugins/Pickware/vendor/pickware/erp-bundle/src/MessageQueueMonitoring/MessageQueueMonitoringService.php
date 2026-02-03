<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\MessageQueueMonitoring;

use DateTime;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MessageQueueMonitoringService
{
    // Since this is a global monitoring service we use a single entry (row) to monitor the message queue.
    // This is the ID for this single row.
    public const LOGGING_ID = '00000000000000000000000000000001';
    public const CLI_WORKER_TIME_THRESHOLD_STRING = '-10 min';
    public const ADMINISTRATION_LOGIN_TIME_THRESHOLD_STRING = '-1 day';
    public const NO_WORKER_RUNNING = 'no_worker_running';
    public const ADMIN_WORKER_RUNNING_OUTDATED = 'admin_worker_running_outdated';
    public const WORKER_RUNNING = 'worker_running';

    private Connection $connection;
    private bool $isAdminWorkerActive;

    public function __construct(
        Connection $connection,
        #[Autowire('%shopware.admin_worker.enable_admin_worker%')]
        bool $isAdminWorkerActive,
    ) {
        $this->connection = $connection;
        $this->isAdminWorkerActive = $isAdminWorkerActive;
    }

    public function logCLIWorkerRun(): void
    {
        $this->connection->executeStatement(
            'INSERT INTO pickware_erp_message_queue_monitoring (
                id,
                last_cli_worker_run
            ) VALUES (
                UNHEX(:loggingId),
                :date
            ) ON DUPLICATE KEY UPDATE last_cli_worker_run = VALUES(last_cli_worker_run);',
            [
                'loggingId' => self::LOGGING_ID,
                'date' => date_format(new DateTime(), 'Y-m-d H:i:s'),
            ],
        );
    }

    public function logAdministrationLogin(): void
    {
        $this->connection->executeStatement(
            'INSERT INTO pickware_erp_message_queue_monitoring (
                id,
                last_administration_login
            ) VALUES (
                UNHEX(:loggingId),
                :date
            ) ON DUPLICATE KEY UPDATE last_administration_login = VALUES(last_administration_login);',
            [
                'loggingId' => self::LOGGING_ID,
                'date' => date_format(new DateTime(), 'Y-m-d H:i:s'),
            ],
        );
    }

    public function getStatus(): string
    {
        $lastLogData = $this->connection->fetchAssociative(
            'SELECT last_cli_worker_run, last_administration_login FROM pickware_erp_message_queue_monitoring WHERE id=UNHEX(:loggingId);',
            ['loggingId' => self::LOGGING_ID],
        );

        $cliWorkerRunWithinTimeLimit = $lastLogData
            && $lastLogData['last_cli_worker_run']
            && strtotime($lastLogData['last_cli_worker_run']) >= strtotime(self::CLI_WORKER_TIME_THRESHOLD_STRING);

        $administrationLoginWithinTimeLimit = !$lastLogData
            || !$lastLogData['last_administration_login']
            || strtotime($lastLogData['last_administration_login']) >= strtotime(self::ADMINISTRATION_LOGIN_TIME_THRESHOLD_STRING);

        $state = self::WORKER_RUNNING;
        if (!$cliWorkerRunWithinTimeLimit && !$this->isAdminWorkerActive) {
            $state = self::NO_WORKER_RUNNING;
        } elseif (!$cliWorkerRunWithinTimeLimit && !$administrationLoginWithinTimeLimit) {
            $state = self::ADMIN_WORKER_RUNNING_OUTDATED;
        }

        return $state;
    }
}
