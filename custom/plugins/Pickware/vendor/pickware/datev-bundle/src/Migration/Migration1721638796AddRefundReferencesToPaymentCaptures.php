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

class Migration1721638796AddRefundReferencesToPaymentCaptures extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1721638796;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE
                `pickware_datev_payment_capture`
            ADD COLUMN `return_order_refund_id` BINARY(16) NULL AFTER `state_machine_history_id`,
            ADD COLUMN `return_order_refund_version_id` BINARY(16) NULL AFTER `return_order_refund_id`,
            ADD CONSTRAINT `pickware_datev_return_order_refund_reference.fk.refund`
                FOREIGN KEY (`return_order_refund_id`, `return_order_refund_version_id`)
                REFERENCES `pickware_erp_return_order_refund` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE;',
        );

        $connection->executeStatement(<<<SQL
            CREATE TRIGGER `before_pickware_datev_payment_capture_insert`
            BEFORE INSERT ON `pickware_datev_payment_capture`
            FOR EACH ROW
            BEGIN
                IF (
                    NEW.`order_transaction_id` IS NOT NULL
                        AND NEW.`return_order_refund_id` IS NOT NULL
                ) THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Payment capture FK definition is not valid; up to one FK type can be set at a given time.';
                END IF;
            END
            SQL);
    }

    public function updateDestructive(Connection $connection): void {}
}
