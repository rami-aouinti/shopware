<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1723131335AddPrioritizedShippingMethodAndPrioritizedPaymentMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1723131335;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE `pickware_wms_picking_profile_prioritized_shipping_method` (
                    `id` BINARY(16) NOT NULL,
                    `picking_profile_id` BINARY(16) NOT NULL,
                    `shipping_method_id` BINARY(16) NOT NULL,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `pickware_wms_pp_prioritized_shipping_method.fk.picking_profile`
                        FOREIGN KEY (`picking_profile_id`)
                        REFERENCES `pickware_wms_picking_profile` (`id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE,
                    CONSTRAINT `pickware_wms_pp_prioritized_shipping_method.fk.shipping_method`
                        FOREIGN KEY (`shipping_method_id`)
                        REFERENCES `shipping_method` (`id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );

        $connection->executeStatement(
            <<<SQL
                CREATE TABLE `pickware_wms_picking_profile_prioritized_payment_method` (
                    `id` BINARY(16) NOT NULL,
                    `picking_profile_id` BINARY(16) NOT NULL,
                    `payment_method_id` BINARY(16) NOT NULL,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    CONSTRAINT `pickware_wms_pp_prioritized_payment_method.fk.picking_profile`
                        FOREIGN KEY (`picking_profile_id`)
                        REFERENCES `pickware_wms_picking_profile` (`id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE,
                    CONSTRAINT `pickware_wms_pp_prioritized_payment_method.fk.payment_method`
                        FOREIGN KEY (`payment_method_id`)
                        REFERENCES `payment_method` (`id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );
    }
}
