<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021011LieferzeitenTaskTransitions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021011;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'lieferzeiten_task', 'position_id')) {
            $connection->executeStatement("ALTER TABLE `lieferzeiten_task` ADD COLUMN `position_id` VARCHAR(64) NULL AFTER `initiator`");
        }

        if (!$this->columnExists($connection, 'lieferzeiten_task', 'trigger_key')) {
            $connection->executeStatement("ALTER TABLE `lieferzeiten_task` ADD COLUMN `trigger_key` VARCHAR(255) NULL AFTER `position_id`");
        }

        if (!$this->indexExists($connection, 'lieferzeiten_task', 'idx.lieferzeiten_task.position_trigger')) {
            $connection->executeStatement('CREATE UNIQUE INDEX `idx.lieferzeiten_task.position_trigger` ON `lieferzeiten_task` (`position_id`, `trigger_key`)');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    protected function columnExists(Connection $connection, string $table, string $column): bool
    {
        return (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName AND COLUMN_NAME = :columnName',
            ['tableName' => $table, 'columnName' => $column]
        ) > 0;
    }

    protected function indexExists(Connection $connection, string $table, string $index): bool
    {
        return (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName AND INDEX_NAME = :indexName',
            ['tableName' => $table, 'indexName' => $index]
        ) > 0;
    }
}
