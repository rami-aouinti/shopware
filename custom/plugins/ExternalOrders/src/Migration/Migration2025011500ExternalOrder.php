<?php declare(strict_types=1);

namespace ExternalOrders\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2025011500ExternalOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2025011500;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `external_order` (
                `id` BINARY(16) NOT NULL,
                `external_id` VARCHAR(255) NOT NULL,
                `payload` JSON NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.external_order.external_id` (`external_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
