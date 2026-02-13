<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021007DeliveryDateRangeHistory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021007;
    }

    public function update(Connection $connection): void
    {
        $this->addColumnIfNotExists(
            $connection,
            'lieferzeiten_liefertermin_lieferant_history',
            'liefertermin_from',
            'DATETIME(3) NULL AFTER `position_id`'
        );
        $this->addColumnIfNotExists(
            $connection,
            'lieferzeiten_liefertermin_lieferant_history',
            'liefertermin_to',
            'DATETIME(3) NULL AFTER `liefertermin_from`'
        );
        $this->addColumnIfNotExists(
            $connection,
            'lieferzeiten_neuer_liefertermin_history',
            'liefertermin_from',
            'DATETIME(3) NULL AFTER `position_id`'
        );
        $this->addColumnIfNotExists(
            $connection,
            'lieferzeiten_neuer_liefertermin_history',
            'liefertermin_to',
            'DATETIME(3) NULL AFTER `liefertermin_from`'
        );

        $connection->executeStatement('UPDATE `lieferzeiten_liefertermin_lieferant_history` SET `liefertermin_from` = `liefertermin`, `liefertermin_to` = `liefertermin` WHERE `liefertermin` IS NOT NULL');
        $connection->executeStatement('UPDATE `lieferzeiten_neuer_liefertermin_history` SET `liefertermin_from` = `liefertermin`, `liefertermin_to` = `liefertermin` WHERE `liefertermin` IS NOT NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addColumnIfNotExists(
        Connection $connection,
        string $table,
        string $column,
        string $definition
    ): void {
        if (!$this->historyColumnExists($connection, $table, $column)) {
            $connection->executeStatement(sprintf(
                'ALTER TABLE `%s` ADD COLUMN `%s` %s',
                $table,
                $column,
                $definition
            ));
        }
    }

    private function historyColumnExists(Connection $connection, string $table, string $column): bool
    {
        $columnName = $connection->fetchOne(
            'SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
            [
                'table' => $table,
                'column' => $column,
            ]
        );

        return $columnName !== false;
    }
}
