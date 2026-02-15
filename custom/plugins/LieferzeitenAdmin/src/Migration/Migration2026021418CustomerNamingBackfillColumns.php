<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021418CustomerNamingBackfillColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021418;
    }

    public function update(Connection $connection): void
    {
        $this->addColumnIfMissing($connection, 'lieferzeiten_paket', 'customer_email', 'VARCHAR(255) NULL AFTER `source_system`');
        $this->addColumnIfMissing($connection, 'lieferzeiten_paket', 'customer_first_name', 'VARCHAR(255) NULL AFTER `customer_email`');
        $this->addColumnIfMissing($connection, 'lieferzeiten_paket', 'customer_last_name', 'VARCHAR(255) NULL AFTER `customer_first_name`');
        $this->addColumnIfMissing($connection, 'lieferzeiten_paket', 'customer_additional_name', 'VARCHAR(255) NULL AFTER `customer_last_name`');
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addColumnIfMissing(Connection $connection, string $table, string $column, string $definition): void
    {
        if ($this->hasColumn($connection, $table, $column)) {
            return;
        }

        $connection->executeStatement(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
    }

    private function hasColumn(Connection $connection, string $table, string $column): bool
    {
        $result = $connection->fetchOne(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
            ['table' => $table, 'column' => $column]
        );

        return (int) $result > 0;
    }
}
