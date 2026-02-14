<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021400ChannelThresholdSettings extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021400;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `lieferzeiten_channel_settings`
            ADD COLUMN IF NOT EXISTS `shipping_working_days` INT NULL AFTER `enable_notifications`,
            ADD COLUMN IF NOT EXISTS `shipping_cutoff` VARCHAR(5) NULL AFTER `shipping_working_days`,
            ADD COLUMN IF NOT EXISTS `delivery_working_days` INT NULL AFTER `shipping_cutoff`,
            ADD COLUMN IF NOT EXISTS `delivery_cutoff` VARCHAR(5) NULL AFTER `delivery_working_days`');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
