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
        $connection->executeStatement('ALTER TABLE `lieferzeiten_liefertermin_lieferant_history` ADD COLUMN `liefertermin_from` DATETIME(3) NULL AFTER `position_id`');
        $connection->executeStatement('ALTER TABLE `lieferzeiten_liefertermin_lieferant_history` ADD COLUMN `liefertermin_to` DATETIME(3) NULL AFTER `liefertermin_from`');
        $connection->executeStatement('ALTER TABLE `lieferzeiten_neuer_liefertermin_history` ADD COLUMN `liefertermin_from` DATETIME(3) NULL AFTER `position_id`');
        $connection->executeStatement('ALTER TABLE `lieferzeiten_neuer_liefertermin_history` ADD COLUMN `liefertermin_to` DATETIME(3) NULL AFTER `liefertermin_from`');

        $connection->executeStatement('UPDATE `lieferzeiten_liefertermin_lieferant_history` SET `liefertermin_from` = `liefertermin`, `liefertermin_to` = `liefertermin` WHERE `liefertermin` IS NOT NULL');
        $connection->executeStatement('UPDATE `lieferzeiten_neuer_liefertermin_history` SET `liefertermin_from` = `liefertermin`, `liefertermin_to` = `liefertermin` WHERE `liefertermin` IS NOT NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
