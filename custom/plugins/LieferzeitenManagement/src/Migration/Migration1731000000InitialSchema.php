<?php declare(strict_types=1);

namespace LieferzeitenManagement\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1731000000InitialSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1731000000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `lieferzeiten_order_position` (
                `id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `order_line_item_id` BINARY(16) NOT NULL,
                `san6_order_number` VARCHAR(64) NULL,
                `san6_position_number` VARCHAR(64) NULL,
                `quantity` INT NULL,
                `supplier_delivery_start` DATE NULL,
                `supplier_delivery_end` DATE NULL,
                `supplier_delivery_comment` LONGTEXT NULL,
                `supplier_delivery_updated_by_id` BINARY(16) NULL,
                `supplier_delivery_updated_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_order_position.order_id` (`order_id`),
                KEY `idx.lieferzeiten_order_position.order_line_item_id` (`order_line_item_id`),
                KEY `idx.lieferzeiten_order_position.supplier_delivery_updated_by_id` (`supplier_delivery_updated_by_id`),
                CONSTRAINT `fk.lieferzeiten_order_position.order_id` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.lieferzeiten_order_position.order_line_item_id` FOREIGN KEY (`order_line_item_id`) REFERENCES `order_line_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.lieferzeiten_order_position.supplier_delivery_updated_by_id` FOREIGN KEY (`supplier_delivery_updated_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `lieferzeiten_package` (
                `id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `san6_package_number` VARCHAR(64) NULL,
                `package_status` VARCHAR(255) NULL,
                `latest_shipping_at` DATETIME(3) NULL,
                `latest_delivery_at` DATETIME(3) NULL,
                `shipped_at` DATETIME(3) NULL,
                `delivered_at` DATETIME(3) NULL,
                `tracking_number` VARCHAR(255) NULL,
                `tracking_provider` VARCHAR(255) NULL,
                `tracking_status` VARCHAR(255) NULL,
                `new_delivery_start` DATE NULL,
                `new_delivery_end` DATE NULL,
                `new_delivery_comment` LONGTEXT NULL,
                `new_delivery_updated_by_id` BINARY(16) NULL,
                `new_delivery_updated_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_package.order_id` (`order_id`),
                KEY `idx.lieferzeiten_package.new_delivery_updated_by_id` (`new_delivery_updated_by_id`),
                CONSTRAINT `fk.lieferzeiten_package.order_id` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.lieferzeiten_package.new_delivery_updated_by_id` FOREIGN KEY (`new_delivery_updated_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `lieferzeiten_package_position` (
                `id` BINARY(16) NOT NULL,
                `package_id` BINARY(16) NOT NULL,
                `order_position_id` BINARY(16) NOT NULL,
                `quantity` INT NULL,
                `split_type` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_package_position.package_id` (`package_id`),
                KEY `idx.lieferzeiten_package_position.order_position_id` (`order_position_id`),
                CONSTRAINT `fk.lieferzeiten_package_position.package_id` FOREIGN KEY (`package_id`) REFERENCES `lieferzeiten_package` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.lieferzeiten_package_position.order_position_id` FOREIGN KEY (`order_position_id`) REFERENCES `lieferzeiten_order_position` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `lieferzeiten_date_history` (
                `id` BINARY(16) NOT NULL,
                `order_position_id` BINARY(16) NULL,
                `package_id` BINARY(16) NULL,
                `type` VARCHAR(255) NULL,
                `range_start` DATE NULL,
                `range_end` DATE NULL,
                `comment` LONGTEXT NULL,
                `created_by_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_date_history.order_position_id` (`order_position_id`),
                KEY `idx.lieferzeiten_date_history.package_id` (`package_id`),
                KEY `idx.lieferzeiten_date_history.created_by_id` (`created_by_id`),
                CONSTRAINT `fk.lieferzeiten_date_history.order_position_id` FOREIGN KEY (`order_position_id`) REFERENCES `lieferzeiten_order_position` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.lieferzeiten_date_history.package_id` FOREIGN KEY (`package_id`) REFERENCES `lieferzeiten_package` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.lieferzeiten_date_history.created_by_id` FOREIGN KEY (`created_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `lieferzeiten_activity_log` (
                `id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `order_position_id` BINARY(16) NULL,
                `package_id` BINARY(16) NULL,
                `action` VARCHAR(255) NULL,
                `payload` JSON NULL,
                `created_by_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_activity_log.order_id` (`order_id`),
                KEY `idx.lieferzeiten_activity_log.order_position_id` (`order_position_id`),
                KEY `idx.lieferzeiten_activity_log.package_id` (`package_id`),
                KEY `idx.lieferzeiten_activity_log.created_by_id` (`created_by_id`),
                CONSTRAINT `fk.lieferzeiten_activity_log.order_id` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.lieferzeiten_activity_log.order_position_id` FOREIGN KEY (`order_position_id`) REFERENCES `lieferzeiten_order_position` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.lieferzeiten_activity_log.package_id` FOREIGN KEY (`package_id`) REFERENCES `lieferzeiten_package` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.lieferzeiten_activity_log.created_by_id` FOREIGN KEY (`created_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `lieferzeiten_task` (
                `id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `order_position_id` BINARY(16) NULL,
                `package_id` BINARY(16) NULL,
                `type` VARCHAR(255) NULL,
                `status` VARCHAR(255) NULL,
                `assigned_user_id` BINARY(16) NULL,
                `due_date` DATETIME(3) NULL,
                `created_by_id` BINARY(16) NULL,
                `completed_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_task.order_id` (`order_id`),
                KEY `idx.lieferzeiten_task.order_position_id` (`order_position_id`),
                KEY `idx.lieferzeiten_task.package_id` (`package_id`),
                KEY `idx.lieferzeiten_task.assigned_user_id` (`assigned_user_id`),
                KEY `idx.lieferzeiten_task.created_by_id` (`created_by_id`),
                CONSTRAINT `fk.lieferzeiten_task.order_id` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.lieferzeiten_task.order_position_id` FOREIGN KEY (`order_position_id`) REFERENCES `lieferzeiten_order_position` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.lieferzeiten_task.package_id` FOREIGN KEY (`package_id`) REFERENCES `lieferzeiten_package` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.lieferzeiten_task.assigned_user_id` FOREIGN KEY (`assigned_user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.lieferzeiten_task.created_by_id` FOREIGN KEY (`created_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `lieferzeiten_settings` (
                `id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NULL,
                `area` VARCHAR(255) NULL,
                `latest_shipping_offset_days` INT NULL,
                `latest_delivery_offset_days` INT NULL,
                `cutoff_time` VARCHAR(32) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_settings.sales_channel_id` (`sales_channel_id`),
                CONSTRAINT `fk.lieferzeiten_settings.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `lieferzeiten_notification_settings` (
                `id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `notification_key` VARCHAR(255) NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.lieferzeiten_notification_settings.sales_channel_id` (`sales_channel_id`),
                CONSTRAINT `fk.lieferzeiten_notification_settings.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
