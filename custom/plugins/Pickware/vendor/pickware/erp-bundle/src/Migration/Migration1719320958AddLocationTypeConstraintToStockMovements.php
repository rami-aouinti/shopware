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

class Migration1719320958AddLocationTypeConstraintToStockMovements extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1719320958;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
            CREATE TRIGGER before_pickware_erp_stock_movement_insert
            BEFORE INSERT ON pickware_erp_stock_movement
            FOR EACH ROW
            BEGIN
                IF (
                    (
                        (
                            # The respective ID for the given source location type is NULL
                            CASE
                                WHEN NEW.source_location_type_technical_name = 'warehouse' THEN NEW.source_warehouse_id IS NULL
                                WHEN NEW.source_location_type_technical_name = 'bin_location' THEN NEW.source_bin_location_id IS NULL
                                WHEN NEW.source_location_type_technical_name = 'order' THEN NEW.source_order_id IS NULL
                                WHEN NEW.source_location_type_technical_name = 'return_order' THEN NEW.source_return_order_id IS NULL
                                WHEN NEW.source_location_type_technical_name = 'special_stock_location' THEN NEW.source_special_stock_location_technical_name IS NULL
                                WHEN NEW.source_location_type_technical_name = 'stock_container' THEN NEW.source_stock_container_id IS NULL
                                WHEN NEW.source_location_type_technical_name = 'goods_receipt' THEN NEW.source_goods_receipt_id IS NULL
                            END = 1
                        )
                        AND
                        # And another source location ID is set.
                        ((NEW.source_warehouse_id IS NOT NULL) + (NEW.source_bin_location_id IS NOT NULL) + (NEW.source_order_id IS NOT NULL) + (NEW.source_return_order_id IS NOT NULL) + (NEW.source_special_stock_location_technical_name IS NOT NULL) + (NEW.source_stock_container_id IS NOT NULL) + (NEW.source_goods_receipt_id IS NOT NULL) > 0)
                    )
                    OR
                    # More than one source location ID is set.
                    ((NEW.source_warehouse_id IS NOT NULL) + (NEW.source_bin_location_id IS NOT NULL) + (NEW.source_order_id IS NOT NULL) + (NEW.source_return_order_id IS NOT NULL) + (NEW.source_special_stock_location_technical_name IS NOT NULL) + (NEW.source_stock_container_id IS NOT NULL) + (NEW.source_goods_receipt_id IS NOT NULL) > 1)
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Provided foreign key does not match source location type';
                END IF;

                IF (
                    (
                        (
                            # The respective ID for the given destination location type is NULL
                            CASE
                                WHEN NEW.destination_location_type_technical_name = 'warehouse' THEN NEW.destination_warehouse_id IS NULL
                                WHEN NEW.destination_location_type_technical_name = 'bin_location' THEN NEW.destination_bin_location_id IS NULL
                                WHEN NEW.destination_location_type_technical_name = 'order' THEN NEW.destination_order_id IS NULL
                                WHEN NEW.destination_location_type_technical_name = 'return_order' THEN NEW.destination_return_order_id IS NULL
                                WHEN NEW.destination_location_type_technical_name = 'special_stock_location' THEN NEW.destination_special_stock_location_technical_name IS NULL
                                WHEN NEW.destination_location_type_technical_name = 'stock_container' THEN NEW.destination_stock_container_id IS NULL
                                WHEN NEW.destination_location_type_technical_name = 'goods_receipt' THEN NEW.destination_goods_receipt_id IS NULL
                            END = 1
                        )
                        AND
                        # And another destination location ID is set.
                        ((NEW.destination_warehouse_id IS NOT NULL) + (NEW.destination_bin_location_id IS NOT NULL) + (NEW.destination_order_id IS NOT NULL) + (NEW.destination_return_order_id IS NOT NULL) + (NEW.destination_special_stock_location_technical_name IS NOT NULL) + (NEW.destination_stock_container_id IS NOT NULL) + (NEW.destination_goods_receipt_id IS NOT NULL) > 0)
                    )
                    OR
                    # More than one destination location ID is set.
                    ((NEW.destination_warehouse_id IS NOT NULL) + (NEW.destination_bin_location_id IS NOT NULL) + (NEW.destination_order_id IS NOT NULL) + (NEW.destination_return_order_id IS NOT NULL) + (NEW.destination_special_stock_location_technical_name IS NOT NULL) + (NEW.destination_stock_container_id IS NOT NULL) + (NEW.destination_goods_receipt_id IS NOT NULL) > 1)
                ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Provided foreign key does not match destination location type';
                END IF;
            END
            SQL);
    }

    public function updateDestructive(Connection $connection): void {}
}
