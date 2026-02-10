<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021006PositionCommentAndRequestFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021006;
    }

    public function update(Connection $connection): void
    {
        $this->addColumnIfMissing($connection, 'comment', 'TEXT NULL AFTER `ordered_at`');
        $this->addColumnIfMissing($connection, 'additional_delivery_request_at', 'DATETIME(3) NULL AFTER `comment`');
        $this->addColumnIfMissing($connection, 'additional_delivery_request_initiator', 'VARCHAR(255) NULL AFTER `additional_delivery_request_at`');
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addColumnIfMissing(Connection $connection, string $column, string $definition): void
    {
        $exists = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
            ['table' => 'lieferzeiten_position', 'column' => $column]
        );

        if ($exists > 0) {
            return;
        }

        $connection->executeStatement(sprintf('ALTER TABLE `lieferzeiten_position` ADD COLUMN `%s` %s', $column, $definition));
    }
}
