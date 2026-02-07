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

class Migration16883860081MigrateOrderStatusFlowActionToForce extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1688386008;
    }

    public function update(Connection $connection): void
    {
        // The given ID refers to the order status flow sequence and must be forced to avoid performing illegal state
        // transitions which can crash transactions and result in very opaque error messages.
        // See https://github.com/pickware/shopware-plugins/issues/4071 for further information.
        // See CreateTransitionOrderToDoneAfterShippingFlowInstallationStep.php for the given id.
        $connection->executeStatement(
            'UPDATE `flow_sequence`
            SET `config` = JSON_SET(`config`, \'$.force_transition\', true)
            WHERE `id` = UNHEX(\'fb4f899646ed403e92e3497f02a3e3a8\')',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
