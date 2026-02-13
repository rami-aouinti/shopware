<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021004AddIsTestOrderFlag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021004;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `lieferzeiten_paket`
                ADD COLUMN IF NOT EXISTS `is_test_order` TINYINT(1) NOT NULL DEFAULT 0
                AFTER `sync_badge`'
        );

        $indexExists = (int) $connection->fetchOne(
            "SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lieferzeiten_paket' AND INDEX_NAME = 'idx.lieferzeiten_paket.is_test_order'"
        );

        if ($indexExists === 0) {
            $connection->executeStatement(
                'CREATE INDEX `idx.lieferzeiten_paket.is_test_order`
                    ON `lieferzeiten_paket` (`is_test_order`)'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
