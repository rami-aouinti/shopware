<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1732813181UseUtcTimestampAsDefaultValues extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1732813181;
    }

    public function update(Connection $connection): void
    {
        $tables = [
            'pickware_wms_delivery_document_mapping',
            'pickware_wms_delivery_order_document_mapping',
        ];

        foreach ($tables as $table) {
            $connection->executeStatement(<<<SQL
                ALTER TABLE `{$table}`
                    CHANGE `created_at` `created_at` DATETIME(3) NOT NULL DEFAULT (UTC_TIMESTAMP(3));
                SQL);
        }
    }

    public function updateDestructive(Connection $connection): void {}
}
