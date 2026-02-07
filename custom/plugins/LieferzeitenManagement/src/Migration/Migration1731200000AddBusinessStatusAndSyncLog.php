<?php declare(strict_types=1);

namespace LieferzeitenManagement\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1731200000AddBusinessStatusAndSyncLog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1731200000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
ALTER TABLE `lieferzeiten_package`
    ADD COLUMN `business_status_code` INT NULL AFTER `tracking_status`,
    ADD COLUMN `business_status_label` VARCHAR(255) NULL AFTER `business_status_code`,
    ADD COLUMN `business_status_source` VARCHAR(64) NULL AFTER `business_status_label`;
SQL
        );

        $connection->executeStatement(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `lieferzeiten_sync_log` (
    `id` BINARY(16) NOT NULL,
    `order_id` BINARY(16) NOT NULL,
    `status_code` INT NOT NULL,
    `source` VARCHAR(64) NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.lieferzeiten_sync_log.order_status_source` (`order_id`, `status_code`, `source`),
    CONSTRAINT `fk.lieferzeiten_sync_log.order_id`
        FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
