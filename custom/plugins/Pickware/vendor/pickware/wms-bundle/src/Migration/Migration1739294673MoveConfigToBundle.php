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

class Migration1739294673MoveConfigToBundle extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1739294673;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                UPDATE `system_config`
                SET `configuration_key` = REPLACE(`configuration_key`, 'PickwareWms.', 'PickwareWmsBundle.')
                WHERE `configuration_key` LIKE 'PickwareWms.%';
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
