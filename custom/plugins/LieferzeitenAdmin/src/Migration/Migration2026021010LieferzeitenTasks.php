<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021010LieferzeitenTasks extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021010;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `lieferzeiten_task` (
                `id` BINARY(16) NOT NULL,
                `status` VARCHAR(64) NOT NULL,
                `assignee` VARCHAR(255) NULL,
                `due_date` DATETIME(3) NULL,
                `initiator` VARCHAR(255) NULL,
                `payload` JSON NOT NULL,
                `closed_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_task.status` (`status`),
                KEY `idx.lieferzeiten_task.assignee` (`assignee`),
                KEY `idx.lieferzeiten_task.due_date` (`due_date`),
                KEY `idx.lieferzeiten_task.closed_at` (`closed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
