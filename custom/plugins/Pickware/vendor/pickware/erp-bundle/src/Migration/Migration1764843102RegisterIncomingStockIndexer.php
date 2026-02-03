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
use Pickware\PickwareErpStarter\SupplierOrder\Indexer\IncomingStockIndexer;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1764843102RegisterIncomingStockIndexer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1764843102;
    }

    public function update(Connection $connection): void
    {
        $this->registerIndexer($connection, IncomingStockIndexer::NAME);
    }

    public function updateDestructive(Connection $connection): void {}
}
