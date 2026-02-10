<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021009OrderOverviewHistoryRangeIndexes extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021009;
    }

    public function update(Connection $connection): void
    {
        $this->addIndexIfMissing(
            $connection,
            'lieferzeiten_liefertermin_lieferant_history',
            'idx.lz_liefertermin_lieferant_history.position_created_range',
            '`position_id`, `created_at`, `liefertermin_from`, `liefertermin_to`'
        );

        $this->addIndexIfMissing(
            $connection,
            'lieferzeiten_neuer_liefertermin_history',
            'idx.lz_neuer_liefertermin_history.position_created_range',
            '`position_id`, `created_at`, `liefertermin_from`, `liefertermin_to`'
        );

        $this->addIndexIfMissing(
            $connection,
            'lieferzeiten_paket',
            'idx.lieferzeiten_paket.payment_date',
            '`payment_date`'
        );

        $this->addIndexIfMissing(
            $connection,
            'lieferzeiten_paket',
            'idx.lieferzeiten_paket.calculated_delivery_date',
            '`calculated_delivery_date`'
        );

        $this->addIndexIfMissing(
            $connection,
            'lieferzeiten_paket',
            'idx.lieferzeiten_paket.business_date_to',
            '`business_date_to`'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
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
