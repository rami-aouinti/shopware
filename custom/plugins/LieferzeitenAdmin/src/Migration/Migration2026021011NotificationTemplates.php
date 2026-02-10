<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021011NotificationTemplates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021011;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `lieferzeiten_notification_template` (
                `id` BINARY(16) NOT NULL,
                `trigger_key` VARCHAR(120) NOT NULL,
                `sales_channel_id` BINARY(16) NULL,
                `language_id` BINARY(16) NULL,
                `subject` VARCHAR(255) NOT NULL,
                `content_html` LONGTEXT NOT NULL,
                `content_plain` LONGTEXT NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.lieferzeiten_notification_template.scope` (`trigger_key`, `sales_channel_id`, `language_id`),
                KEY `idx.lieferzeiten_notification_template.trigger` (`trigger_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
