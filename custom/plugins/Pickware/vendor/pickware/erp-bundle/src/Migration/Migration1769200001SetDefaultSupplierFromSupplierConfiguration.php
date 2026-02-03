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

class Migration1769200001SetDefaultSupplierFromSupplierConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769200001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE `pickware_erp_pickware_product` AS pickwareProduct
            INNER JOIN `pickware_erp_product_supplier_configuration` AS supplierConfiguration
                ON pickwareProduct.`product_id` = supplierConfiguration.`product_id`
                AND pickwareProduct.`product_version_id` = supplierConfiguration.`product_version_id`
            SET pickwareProduct.`default_supplier_id` = supplierConfiguration.`supplier_id`
            WHERE supplierConfiguration.`supplier_is_default` = 1
              AND pickwareProduct.`default_supplier_id` IS NULL;
        ');
    }
}
