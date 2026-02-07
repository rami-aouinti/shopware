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

class Migration1734530770DeleteStocktakingCountingProcessItemGridUserConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1734530770;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            DELETE FROM `user_config`
            WHERE `key` = "pw-erp-stocktaking-stocktake-counting-process-items-grid"
        ');
    }
}
