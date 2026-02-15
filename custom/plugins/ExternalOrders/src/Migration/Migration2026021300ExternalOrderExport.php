<?php declare(strict_types=1);

namespace ExternalOrders\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021300ExternalOrderExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021300;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `external_order_export` (
                `id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `status` VARCHAR(64) NOT NULL,
                `strategy` VARCHAR(64) NOT NULL,
                `attempts` INT NOT NULL DEFAULT 0,
                `request_xml` LONGTEXT NULL,
                `response_xml` LONGTEXT NULL,
                `response_code` INT NULL,
                `response_message` VARCHAR(512) NULL,
                `last_error` VARCHAR(2000) NULL,
                `correlation_id` VARCHAR(64) NOT NULL,
                `next_retry_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.external_order_export.order_id` (`order_id`),
                KEY `idx.external_order_export.retry` (`status`, `next_retry_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
