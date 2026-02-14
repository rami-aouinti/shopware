<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021420TrackingCarrierHistory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021420;
    }

    public function update(Connection $connection): void
    {
        $exists = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName AND COLUMN_NAME = :columnName',
            ['tableName' => 'lieferzeiten_sendenummer_history', 'columnName' => 'carrier']
        );

        if ($exists > 0) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `lieferzeiten_sendenummer_history` ADD COLUMN `carrier` VARCHAR(255) NULL AFTER `sendenummer`');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
