<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021000LieferzeitenImportEnhancements extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021000;
    }

    public function update(Connection $connection): void
    {
        $columns = [
            'external_order_id' => 'VARCHAR(255) NULL',
            'source_system' => 'VARCHAR(64) NULL',
            'customer_email' => 'VARCHAR(255) NULL',
            'payment_method' => 'VARCHAR(255) NULL',
            'payment_date' => 'DATETIME(3) NULL',
            'base_date_type' => 'VARCHAR(64) NULL',
            'order_date' => 'DATETIME(3) NULL',
            'calculated_delivery_date' => 'DATETIME(3) NULL',
            'delivery_date' => 'DATETIME(3) NULL',
            'sync_badge' => 'VARCHAR(64) NULL',
            'status_push_queue' => 'JSON NULL',
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists($connection, 'lieferzeiten_paket', $column)) {
                $connection->executeStatement(sprintf('ALTER TABLE `lieferzeiten_paket` ADD COLUMN `%s` %s', $column, $definition));
            }
        }

        if (!$this->indexExists($connection, 'lieferzeiten_paket', 'idx.lieferzeiten_paket.external_order_id')) {
            $connection->executeStatement('CREATE INDEX `idx.lieferzeiten_paket.external_order_id` ON `lieferzeiten_paket` (`external_order_id`)');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    protected function columnExists(Connection $connection, string $table, string $column): bool
    {
        $columns = $connection->createSchemaManager()->listTableColumns($table);

        return isset($columns[$column]);
    }

    protected function indexExists(Connection $connection, string $table, string $index): bool
    {
        $indexes = $connection->createSchemaManager()->listTableIndexes($table);

        return isset($indexes[$index]);
    }
}
