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
use function Pickware\InstallationLibrary\Migration\dropIndexIfExists;

use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1761300578CleanupOrphanedIndicesFromForeignKeyDrops extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1761300578;
    }

    public function update(Connection $connection): void
    {
        // Cleanup orphaned indices from previous migrations that dropped foreign keys but not their associated indices
        dropIndexIfExists(
            $connection,
            'product',
            'pickware_erp.fk.pickwareErpPickingProperties',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
