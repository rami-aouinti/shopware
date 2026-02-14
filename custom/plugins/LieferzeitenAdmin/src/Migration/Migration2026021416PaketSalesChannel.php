<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021416PaketSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021416;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'lieferzeiten_paket', 'sales_channel_id')) {
            $connection->executeStatement('ALTER TABLE `lieferzeiten_paket` ADD COLUMN `sales_channel_id` VARCHAR(64) NULL AFTER `source_system`');
        }

        if (!$this->hasIndex($connection, 'lieferzeiten_paket', 'idx.lieferzeiten_paket.sales_channel_id')) {
            $connection->executeStatement('CREATE INDEX `idx.lieferzeiten_paket.sales_channel_id` ON `lieferzeiten_paket` (`sales_channel_id`)');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function columnExists(Connection $connection, string $tableName, string $columnName): bool
    {
        $columns = $connection->createSchemaManager()->listTableColumns($tableName);

        return isset($columns[$columnName]);
    }

    private function hasIndex(Connection $connection, string $tableName, string $indexName): bool
    {
        $indexes = $connection->createSchemaManager()->listTableIndexes($tableName);

        return isset($indexes[$indexName]);
    }
}
