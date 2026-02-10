<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021002TaskAssignmentRuleEngine extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021002;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'lieferzeiten_task_assignment_rule', 'trigger_key')) {
            $connection->executeStatement("ALTER TABLE `lieferzeiten_task_assignment_rule` ADD COLUMN `trigger_key` VARCHAR(255) NULL AFTER `status`");
        }

        if (!$this->columnExists($connection, 'lieferzeiten_task_assignment_rule', 'rule_id')) {
            $connection->executeStatement("ALTER TABLE `lieferzeiten_task_assignment_rule` ADD COLUMN `rule_id` BINARY(16) NULL AFTER `trigger_key`");
        }

        if (!$this->columnExists($connection, 'lieferzeiten_task_assignment_rule', 'assignee_type')) {
            $connection->executeStatement("ALTER TABLE `lieferzeiten_task_assignment_rule` ADD COLUMN `assignee_type` VARCHAR(64) NULL AFTER `rule_id`");
        }

        if (!$this->columnExists($connection, 'lieferzeiten_task_assignment_rule', 'assignee_identifier')) {
            $connection->executeStatement("ALTER TABLE `lieferzeiten_task_assignment_rule` ADD COLUMN `assignee_identifier` VARCHAR(255) NULL AFTER `assignee_type`");
        }

        if (!$this->indexExists($connection, 'lieferzeiten_task_assignment_rule', 'idx.lieferzeiten_task_assignment_rule.trigger_key')) {
            $connection->executeStatement('CREATE INDEX `idx.lieferzeiten_task_assignment_rule.trigger_key` ON `lieferzeiten_task_assignment_rule` (`trigger_key`)');
        }

        if (!$this->indexExists($connection, 'lieferzeiten_task_assignment_rule', 'idx.lieferzeiten_task_assignment_rule.rule_id')) {
            $connection->executeStatement('CREATE INDEX `idx.lieferzeiten_task_assignment_rule.rule_id` ON `lieferzeiten_task_assignment_rule` (`rule_id`)');
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
