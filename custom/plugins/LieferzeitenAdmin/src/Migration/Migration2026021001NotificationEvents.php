<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021001NotificationEvents extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021001;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'lieferzeiten_notification_toggle', 'trigger_key')) {
            $connection->executeStatement("ALTER TABLE `lieferzeiten_notification_toggle` ADD COLUMN `trigger_key` VARCHAR(255) NOT NULL DEFAULT 'legacy'");
        }

        if (!$this->columnExists($connection, 'lieferzeiten_notification_toggle', 'channel')) {
            $connection->executeStatement("ALTER TABLE `lieferzeiten_notification_toggle` ADD COLUMN `channel` VARCHAR(64) NOT NULL DEFAULT 'email'");
        }

        if (!$this->columnExists($connection, 'lieferzeiten_notification_toggle', 'sales_channel_id')) {
            $connection->executeStatement('ALTER TABLE `lieferzeiten_notification_toggle` ADD COLUMN `sales_channel_id` VARCHAR(64) NULL');
        }

        $connection->executeStatement("UPDATE `lieferzeiten_notification_toggle` SET `trigger_key` = IFNULL(`code`, 'legacy') WHERE `trigger_key` = 'legacy'");
        $connection->executeStatement("UPDATE `lieferzeiten_notification_toggle` SET `code` = CONCAT(`trigger_key`, ':', `channel`) WHERE `code` IS NULL OR `code` = ''");

        if (!$this->indexExists($connection, 'lieferzeiten_notification_toggle', 'uniq.lieferzeiten_notification_toggle.event_channel_scope')) {
            $connection->executeStatement('CREATE UNIQUE INDEX `uniq.lieferzeiten_notification_toggle.event_channel_scope` ON `lieferzeiten_notification_toggle` (`trigger_key`, `channel`, `sales_channel_id`)');
        }

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `lieferzeiten_notification_event` (
                `id` BINARY(16) NOT NULL,
                `event_key` VARCHAR(255) NOT NULL,
                `trigger_key` VARCHAR(255) NOT NULL,
                `channel` VARCHAR(64) NOT NULL,
                `external_order_id` VARCHAR(255) NULL,
                `source_system` VARCHAR(64) NULL,
                `payload` JSON NOT NULL,
                `status` VARCHAR(64) NOT NULL,
                `dispatched_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.lieferzeiten_notification_event.event_key` (`event_key`),
                KEY `idx.lieferzeiten_notification_event.trigger_key` (`trigger_key`),
                KEY `idx.lieferzeiten_notification_event.channel` (`channel`),
                KEY `idx.lieferzeiten_notification_event.external_order_id` (`external_order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    protected function columnExists(Connection $connection, string $table, string $column): bool
    {
        return isset($connection->createSchemaManager()->listTableColumns($table)[$column]);
    }

    protected function indexExists(Connection $connection, string $table, string $index): bool
    {
        return isset($connection->createSchemaManager()->listTableIndexes($table)[$index]);
    }
}
