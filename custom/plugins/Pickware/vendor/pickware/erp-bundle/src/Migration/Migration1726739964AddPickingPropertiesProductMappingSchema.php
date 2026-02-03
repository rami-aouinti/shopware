<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1726739964AddPickingPropertiesProductMappingSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1726739964;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE `pickware_erp_picking_property_product_mapping` (
                    `picking_property_id` BINARY(16) NOT NULL,
                    `product_id` BINARY(16) NOT NULL,
                    `product_version_id` BINARY(16) NOT NULL,
                    PRIMARY KEY (`picking_property_id`, `product_id`, `product_version_id`),
                    CONSTRAINT `pickware_erp_picking_property_product_mapping.fk.property`
                        FOREIGN KEY (`picking_property_id`)
                        REFERENCES `pickware_erp_picking_property` (`id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE,
                    CONSTRAINT `pickware_erp_picking_property_product_mapping.fk.product`
                        FOREIGN KEY (`product_id`, `product_version_id`)
                        REFERENCES `product` (`id`, `version_id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );
    }
}
