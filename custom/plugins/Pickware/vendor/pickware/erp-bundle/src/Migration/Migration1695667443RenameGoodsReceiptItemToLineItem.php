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

class Migration1695667443RenameGoodsReceiptItemToLineItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1695667443;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            /** @lang SQL */
            'RENAME TABLE `pickware_erp_goods_receipt_item` TO `pickware_erp_goods_receipt_line_item`',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
