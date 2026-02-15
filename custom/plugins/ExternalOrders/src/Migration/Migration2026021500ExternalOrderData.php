<?php declare(strict_types=1);

namespace ExternalOrders\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021500ExternalOrderData extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021500;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `external_order_data` (
                `id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `external_id` VARCHAR(255) NOT NULL,
                `channel` VARCHAR(64) NULL,
                `raw_payload` JSON NOT NULL,
                `source_status` VARCHAR(64) NULL,
                `source_created_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.external_order_data.order_id` FOREIGN KEY (`order_id`)
                    REFERENCES `order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                UNIQUE KEY `uniq.external_order_data.external_id` (`external_id`),
                KEY `idx.external_order_data.order_id` (`order_id`),
                KEY `idx.external_order_data.channel` (`channel`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
