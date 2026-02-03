<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ProductSetBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1722594437FixStockManagementDisabledFlag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1722594437;
    }

    // The stock management disabled connection (product sets should always be stock management disabled) did break due
    // to a bug in die Administration. This Migration fixes this once.
    // See https://github.com/pickware/shopware-plugins/issues/6870
    public function update(Connection $db): void
    {
        $db->executeStatement(
            'UPDATE `pickware_erp_pickware_product` pp
            INNER JOIN `pickware_product_set_product_set` pset
                ON pp.product_id = pset.product_id AND pp.product_version_id = pset.product_version_id
            SET pp.is_stock_management_disabled = 1;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
