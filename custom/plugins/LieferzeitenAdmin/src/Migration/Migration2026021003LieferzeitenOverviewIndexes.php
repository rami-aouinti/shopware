<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021003LieferzeitenOverviewIndexes extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021003;
    }

    public function update(Connection $connection): void
    {
        if (!$this->indexExists($connection, 'lieferzeiten_paket', 'idx.lieferzeiten_paket.order_date')) {
            $connection->executeStatement('CREATE INDEX `idx.lieferzeiten_paket.order_date` ON `lieferzeiten_paket` (`order_date`)');
        }

        if (!$this->indexExists($connection, 'lieferzeiten_paket', 'idx.lieferzeiten_paket.delivery_date')) {
            $connection->executeStatement('CREATE INDEX `idx.lieferzeiten_paket.delivery_date` ON `lieferzeiten_paket` (`delivery_date`)');
        }

        if (!$this->indexExists($connection, 'lieferzeiten_paket', 'idx.lieferzeiten_paket.last_changed_by')) {
            $connection->executeStatement('CREATE INDEX `idx.lieferzeiten_paket.last_changed_by` ON `lieferzeiten_paket` (`last_changed_by`)');
        }

        if (!$this->indexExists($connection, 'lieferzeiten_position', 'idx.lieferzeiten_position.paket_id_status')) {
            $connection->executeStatement('CREATE INDEX `idx.lieferzeiten_position.paket_id_status` ON `lieferzeiten_position` (`paket_id`, `status`)');
        }

        if (!$this->indexExists($connection, 'lieferzeiten_sendenummer_history', 'idx.lieferzeiten_sh.position_id_sendenummer')) {
            $connection->executeStatement('CREATE INDEX `idx.lieferzeiten_sh.position_id_sendenummer` ON `lieferzeiten_sendenummer_history` (`position_id`, `sendenummer`)');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function indexExists(Connection $connection, string $table, string $index): bool
    {
        $indexes = $connection->createSchemaManager()->listTableIndexes($table);

        return isset($indexes[$index]);
    }
}
