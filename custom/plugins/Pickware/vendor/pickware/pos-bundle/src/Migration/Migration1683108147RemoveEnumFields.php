<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1683108147RemoveEnumFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1683108147;
    }

    /**
     * In order to comply with the Doctrine schema validation, we migrate all ENUM-fields here as they are not supported
     * by Doctrine. This is necessary, because this validation is also executed when third-party apps that use custom
     * entities are installed. See also https://github.com/pickware/shopware-plugins/issues/3732
     */
    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_pos_cash_point_closing_transaction`
            CHANGE `type` `type` VARCHAR(255) NOT NULL;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_pos_cash_point_closing_transaction_line_item`
            CHANGE `type` `type` VARCHAR(255) NOT NULL;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
