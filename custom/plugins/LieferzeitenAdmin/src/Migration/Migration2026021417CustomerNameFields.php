<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021417CustomerNameFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021417;
    }

    public function update(Connection $connection): void
    {
        $this->addColumnIfMissing($connection, 'lieferzeiten_paket', 'customer_first_name', 'VARCHAR(255) NULL AFTER `customer_email`');
        $this->addColumnIfMissing($connection, 'lieferzeiten_paket', 'customer_last_name', 'VARCHAR(255) NULL AFTER `customer_first_name`');
        $this->addColumnIfMissing($connection, 'lieferzeiten_paket', 'customer_additional_name', 'VARCHAR(255) NULL AFTER `customer_last_name`');
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addColumnIfMissing(Connection $connection, string $table, string $column, string $definition): void
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
}
