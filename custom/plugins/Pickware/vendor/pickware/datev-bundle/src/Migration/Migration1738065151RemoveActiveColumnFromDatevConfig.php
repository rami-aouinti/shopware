<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1738065151RemoveActiveColumnFromDatevConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1738065151;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            DELETE FROM `pickware_datev_config`
            WHERE `active` = FALSE
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_datev_config`
            DROP COLUMN `active`
        ');
    }
}
