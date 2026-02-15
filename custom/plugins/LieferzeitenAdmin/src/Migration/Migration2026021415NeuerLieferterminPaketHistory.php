<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2026021415NeuerLieferterminPaketHistory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2026021415;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `lieferzeiten_neuer_liefertermin_paket_history` (
    `id` BINARY(16) NOT NULL,
    `paket_id` BINARY(16) NOT NULL,
    `liefertermin_from` DATETIME(3) NULL,
    `liefertermin_to` DATETIME(3) NULL,
    `liefertermin` DATETIME(3) NULL,
    `last_changed_by` VARCHAR(255) NULL,
    `last_changed_at` DATETIME(3) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.lieferzeiten_nlph.paket_id` (`paket_id`),
    KEY `idx.lieferzeiten_nlph.liefertermin` (`liefertermin`),
    CONSTRAINT `fk.lieferzeiten_nlph.paket_id` FOREIGN KEY (`paket_id`)
        REFERENCES `lieferzeiten_paket` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

        $connection->executeStatement(<<<'SQL'
INSERT INTO `lieferzeiten_neuer_liefertermin_paket_history` (
    `id`, `paket_id`, `liefertermin_from`, `liefertermin_to`, `liefertermin`,
    `last_changed_by`, `last_changed_at`, `created_at`, `updated_at`
)
SELECT
    UNHEX(REPLACE(UUID(), '-', '')),
    latest_by_paket.paket_id,
    latest_by_paket.liefertermin_from,
    latest_by_paket.liefertermin_to,
    latest_by_paket.liefertermin,
    latest_by_paket.last_changed_by,
    latest_by_paket.last_changed_at,
    latest_by_paket.created_at,
    latest_by_paket.updated_at
FROM (
    SELECT p.paket_id,
           nlh.liefertermin_from,
           nlh.liefertermin_to,
           nlh.liefertermin,
           nlh.last_changed_by,
           nlh.last_changed_at,
           nlh.created_at,
           nlh.updated_at,
           ROW_NUMBER() OVER (PARTITION BY p.paket_id ORDER BY nlh.created_at DESC, nlh.id DESC) AS rn
    FROM `lieferzeiten_neuer_liefertermin_history` nlh
    INNER JOIN `lieferzeiten_position` p ON p.id = nlh.position_id
    WHERE p.paket_id IS NOT NULL
) AS latest_by_paket
LEFT JOIN `lieferzeiten_neuer_liefertermin_paket_history` existing
   ON existing.paket_id = latest_by_paket.paket_id
WHERE latest_by_paket.rn = 1
  AND existing.id IS NULL
SQL
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
