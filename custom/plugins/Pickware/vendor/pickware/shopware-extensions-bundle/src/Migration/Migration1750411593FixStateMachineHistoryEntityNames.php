<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1750411593FixStateMachineHistoryEntityNames extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1750411593;
    }

    public function update(Connection $connection): void
    {
        // We accidentally persisted the FQN instead of the entity name in the BatchedStateTransitionExecutor
        $connection->executeStatement(
            'UPDATE `state_machine_history` SET `entity_name` = :orderEntityName WHERE `entity_name` = :orderFQN;',
            [
                'orderEntityName' => OrderDefinition::ENTITY_NAME,
                'orderFQN' => OrderDefinition::class,
            ],
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
