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

class Migration1726498687FixUniqueIndexInPickwareProduct extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1726498687;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_pickware_product`
                ADD UNIQUE INDEX `pickware_erp_pickware_product.uidx.product` (`product_id`, `product_version_id`),
                DROP INDEX `pickware_erp_product_configuration.uidx.product`;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
