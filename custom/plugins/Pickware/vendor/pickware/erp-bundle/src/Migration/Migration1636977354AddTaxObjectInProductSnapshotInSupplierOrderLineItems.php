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

class Migration1636977354AddTaxObjectInProductSnapshotInSupplierOrderLineItems extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1636977354;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE
                pickware_erp_supplier_order_line_item
            LEFT JOIN product
                ON pickware_erp_supplier_order_line_item.product_id = product.id
            LEFT JOIN tax
                ON product.tax = tax.id
            SET
                pickware_erp_supplier_order_line_item.product_snapshot = JSON_INSERT(
                    pickware_erp_supplier_order_line_item.product_snapshot,
                    "$.tax",
                    JSON_OBJECT(
                        "id", LOWER(HEX(tax.id)),
                        "tax_rate", tax.tax_rate,
                        "name", tax.name,
                        "position", tax.position,
                        "created_at", tax.created_at,
                        "updated_at", tax.updated_at
                    )
                )
            WHERE
                tax_rate IS NOT NULL
                AND JSON_EXTRACT(pickware_erp_supplier_order_line_item.product_snapshot, "$.tax") IS NULL
            ;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
