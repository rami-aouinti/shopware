<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021416ChannelPdmsThresholdConstraints extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021416;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `lieferzeiten_channel_pdms_threshold`
                MODIFY `shipping_overdue_working_days` INT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY `delivery_overdue_working_days` INT UNSIGNED NOT NULL DEFAULT 0'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
