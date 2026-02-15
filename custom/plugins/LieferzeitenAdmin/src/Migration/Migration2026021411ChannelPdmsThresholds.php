<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021411ChannelPdmsThresholds extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021411;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `lieferzeiten_channel_pdms_threshold` (
                `id` BINARY(16) NOT NULL,
                `sales_channel_id` VARCHAR(255) NOT NULL,
                `pdms_lieferzeit` VARCHAR(64) NOT NULL,
                `shipping_overdue_working_days` INT UNSIGNED NOT NULL DEFAULT 0,
                `delivery_overdue_working_days` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.lieferzeiten_channel_pdms_threshold.channel_pdms` (`sales_channel_id`, `pdms_lieferzeit`),
                KEY `idx.lieferzeiten_channel_pdms_threshold.sales_channel_id` (`sales_channel_id`),
                KEY `idx.lieferzeiten_channel_pdms_threshold.pdms_lieferzeit` (`pdms_lieferzeit`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
