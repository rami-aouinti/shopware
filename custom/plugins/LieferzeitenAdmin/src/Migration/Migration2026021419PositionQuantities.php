<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021419PositionQuantities extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021419;
    }

    public function update(Connection $connection): void
    {
        $this->addColumnIfMissing(
            $connection,
            'lieferzeiten_position',
            'ordered_quantity',
            'INT NULL AFTER `ordered_at`'
        );
        $this->addColumnIfMissing(
            $connection,
            'lieferzeiten_position',
            'shipped_quantity',
            'INT NULL AFTER `ordered_quantity`'
        );
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
