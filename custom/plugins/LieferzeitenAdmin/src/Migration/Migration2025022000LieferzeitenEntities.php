<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2025022000LieferzeitenEntities extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2025022000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `lieferzeiten_paket` (
                `id` BINARY(16) NOT NULL,
                `paket_number` VARCHAR(255) NOT NULL,
                `status` VARCHAR(255) NULL,
                `shipping_date` DATETIME(3) NULL,
                `last_changed_by` VARCHAR(255) NULL,
                `last_changed_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_paket.paket_number` (`paket_number`),
                KEY `idx.lieferzeiten_paket.status` (`status`),
                KEY `idx.lieferzeiten_paket.shipping_date` (`shipping_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `lieferzeiten_position` (
                `id` BINARY(16) NOT NULL,
                `position_number` VARCHAR(255) NOT NULL,
                `article_number` VARCHAR(255) NULL,
                `status` VARCHAR(255) NULL,
                `ordered_at` DATETIME(3) NULL,
                `paket_id` BINARY(16) NULL,
                `last_changed_by` VARCHAR(255) NULL,
                `last_changed_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_position.position_number` (`position_number`),
                KEY `idx.lieferzeiten_position.article_number` (`article_number`),
                KEY `idx.lieferzeiten_position.status` (`status`),
                KEY `idx.lieferzeiten_position.ordered_at` (`ordered_at`),
                KEY `idx.lieferzeiten_position.paket_id` (`paket_id`),
                CONSTRAINT `fk.lieferzeiten_position.paket_id` FOREIGN KEY (`paket_id`)
                    REFERENCES `lieferzeiten_paket` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `lieferzeiten_liefertermin_lieferant_history` (
                `id` BINARY(16) NOT NULL,
                `position_id` BINARY(16) NOT NULL,
                `liefertermin` DATETIME(3) NULL,
                `last_changed_by` VARCHAR(255) NULL,
                `last_changed_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_llh.position_id` (`position_id`),
                KEY `idx.lieferzeiten_llh.liefertermin` (`liefertermin`),
                CONSTRAINT `fk.lieferzeiten_llh.position_id` FOREIGN KEY (`position_id`)
                    REFERENCES `lieferzeiten_position` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `lieferzeiten_neuer_liefertermin_history` (
                `id` BINARY(16) NOT NULL,
                `position_id` BINARY(16) NOT NULL,
                `liefertermin` DATETIME(3) NULL,
                `last_changed_by` VARCHAR(255) NULL,
                `last_changed_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_nlh.position_id` (`position_id`),
                KEY `idx.lieferzeiten_nlh.liefertermin` (`liefertermin`),
                CONSTRAINT `fk.lieferzeiten_nlh.position_id` FOREIGN KEY (`position_id`)
                    REFERENCES `lieferzeiten_position` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `lieferzeiten_sendenummer_history` (
                `id` BINARY(16) NOT NULL,
                `position_id` BINARY(16) NOT NULL,
                `sendenummer` VARCHAR(255) NULL,
                `last_changed_by` VARCHAR(255) NULL,
                `last_changed_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_sh.position_id` (`position_id`),
                KEY `idx.lieferzeiten_sh.sendenummer` (`sendenummer`),
                CONSTRAINT `fk.lieferzeiten_sh.position_id` FOREIGN KEY (`position_id`)
                    REFERENCES `lieferzeiten_position` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `lieferzeiten_channel_settings` (
                `id` BINARY(16) NOT NULL,
                `sales_channel_id` VARCHAR(255) NOT NULL,
                `default_status` VARCHAR(255) NULL,
                `enable_notifications` TINYINT(1) NOT NULL DEFAULT 0,
                `last_changed_by` VARCHAR(255) NULL,
                `last_changed_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_channel_settings.sales_channel_id` (`sales_channel_id`),
                KEY `idx.lieferzeiten_channel_settings.default_status` (`default_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `lieferzeiten_task_assignment_rule` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `status` VARCHAR(255) NULL,
                `priority` INT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 0,
                `conditions` JSON NULL,
                `last_changed_by` VARCHAR(255) NULL,
                `last_changed_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_task_assignment_rule.status` (`status`),
                KEY `idx.lieferzeiten_task_assignment_rule.active` (`active`),
                KEY `idx.lieferzeiten_task_assignment_rule.priority` (`priority`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `lieferzeiten_notification_toggle` (
                `id` BINARY(16) NOT NULL,
                `code` VARCHAR(255) NOT NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `last_changed_by` VARCHAR(255) NULL,
                `last_changed_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_notification_toggle.code` (`code`),
                KEY `idx.lieferzeiten_notification_toggle.enabled` (`enabled`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
