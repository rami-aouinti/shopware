<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1695123051CreateDatevPaymentCaptureSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1695123051;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE `pickware_datev_payment_capture` (
                `id` BINARY(16) NOT NULL,
                `type` VARCHAR(255) NOT NULL,
                `amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `original_amount` DECIMAL(10,2) NULL,
                `currency_id` BINARY(16) NOT NULL,
                `export_comment` VARCHAR(255) NULL,
                `internal_comment` LONGTEXT NULL,
                `transaction_reference` VARCHAR(255) NULL,
                `transaction_date` DATETIME(3) NOT NULL,
                `order_id` BINARY(16) NULL,
                `order_version_id` BINARY(16) NULL,
                `order_transaction_id` BINARY(16) NULL,
                `order_transaction_version_id` BINARY(16) NULL,
                `state_machine_history_id` BINARY(16) NULL,
                `user_id` BINARY(16) NULL,
                `user_snapshot` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) DEFAULT NULL,
                `is_not_related_to_state_machine_history_entry` INT(1) GENERATED ALWAYS AS (IF(state_machine_history_id IS NULL, 1, NULL)) VIRTUAL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_datev_payment_capture.uidx.order_transaction` (`order_transaction_id`, `order_transaction_version_id`, `is_not_related_to_state_machine_history_entry`),
                UNIQUE INDEX `pickware_datev_payment_capture.uidx.state_machine_history` (`state_machine_history_id`),
                CONSTRAINT `pickware_datev_payment_capture.fk.currency`
                    FOREIGN KEY (`currency_id`)
                    REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_datev_payment_capture.fk.order`
                    FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_datev_payment_capture.fk.order_transaction`
                    FOREIGN KEY (`order_transaction_id`, `order_transaction_version_id`)
                    REFERENCES `order_transaction` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_datev_payment_capture.fk.state_machine_history`
                    FOREIGN KEY (`state_machine_history_id`)
                    REFERENCES `state_machine_history` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_datev_payment_capture.fk.user`
                    FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
