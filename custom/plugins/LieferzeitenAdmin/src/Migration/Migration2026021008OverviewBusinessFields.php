<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021008OverviewBusinessFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021008;
    }

    public function update(Connection $connection): void
    {
        $this->addColumnIfMissing($connection, 'lieferzeiten_position', 'current_comment', 'TEXT NULL AFTER `comment`');

        $this->addColumnIfMissing($connection, 'lieferzeiten_paket', 'shipping_assignment_type', 'VARCHAR(64) NULL AFTER `base_date_type`');
        $this->addColumnIfMissing($connection, 'lieferzeiten_paket', 'partial_shipment_quantity', 'VARCHAR(32) NULL AFTER `shipping_assignment_type`');
        $this->addColumnIfMissing($connection, 'lieferzeiten_paket', 'business_date_from', 'DATETIME(3) NULL AFTER `partial_shipment_quantity`');
        $this->addColumnIfMissing($connection, 'lieferzeiten_paket', 'business_date_to', 'DATETIME(3) NULL AFTER `business_date_from`');

        $this->addIndexIfMissing($connection, 'lieferzeiten_paket', 'idx.lieferzeiten_paket.shipping_assignment_type', '`shipping_assignment_type`');
        $this->addIndexIfMissing($connection, 'lieferzeiten_paket', 'idx.lieferzeiten_paket.business_date_from', '`business_date_from`');
        $this->addIndexIfMissing($connection, 'lieferzeiten_paket', 'idx.lieferzeiten_paket.business_date_to', '`business_date_to`');
        $this->addIndexIfMissing($connection, 'lieferzeiten_paket', 'idx.lieferzeiten_paket.overview_date_filters', '`is_test_order`, `shipping_assignment_type`, `order_date`, `shipping_date`, `delivery_date`, `business_date_from`, `business_date_to`');
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    protected function addColumnIfMissing(Connection $connection, string $table, string $column, string $definition): void
    {
        $exists = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName AND COLUMN_NAME = :columnName',
            ['tableName' => $table, 'columnName' => $column]
        );

        if ($exists > 0) {
            return;
        }

        $connection->executeStatement(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
    }

    protected function addIndexIfMissing(Connection $connection, string $table, string $indexName, string $definition): void
    {
        $exists = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName AND INDEX_NAME = :indexName',
            ['tableName' => $table, 'indexName' => $indexName]
        );

        if ($exists > 0) {
            return;
        }

        $connection->executeStatement(sprintf('CREATE INDEX `%s` ON `%s` (%s)', $indexName, $table, $definition));
    }
}
