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
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1733733983CreateShippingMethodConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1733733983;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_wms_shipping_method_config` (
                `id` BINARY(16) NOT NULL,
                `shipping_method_id` BINARY(16) NOT NULL,
                `create_enclosed_return_label` BOOL NOT NULL DEFAULT FALSE,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `pickware_wms_shipping_method_config.uidx.shipping_method_id` (`shipping_method_id`),
                CONSTRAINT `fk.pickware_wms_shipping_method_config.shipping_method_id`
                    FOREIGN KEY (`shipping_method_id`)
                    REFERENCES `shipping_method` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
