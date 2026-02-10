<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021005AuditAndDeadLetter extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021005;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `lieferzeiten_audit_log` (
                `id` BINARY(16) NOT NULL,
                `action` VARCHAR(128) NOT NULL,
                `target_type` VARCHAR(128) NOT NULL,
                `target_id` VARCHAR(255) NULL,
                `source_system` VARCHAR(64) NULL,
                `user_id` VARCHAR(64) NULL,
                `correlation_id` VARCHAR(64) NULL,
                `payload` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.lieferzeiten_audit_log.created_at` (`created_at`),
                INDEX `idx.lieferzeiten_audit_log.action` (`action`),
                INDEX `idx.lieferzeiten_audit_log.source_system` (`source_system`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `lieferzeiten_dead_letter` (
                `id` BINARY(16) NOT NULL,
                `system` VARCHAR(64) NOT NULL,
                `operation` VARCHAR(128) NOT NULL,
                `error_message` TEXT NOT NULL,
                `attempts` INT NOT NULL,
                `correlation_id` VARCHAR(64) NULL,
                `payload` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.lieferzeiten_dead_letter.created_at` (`created_at`),
                INDEX `idx.lieferzeiten_dead_letter.system` (`system`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

