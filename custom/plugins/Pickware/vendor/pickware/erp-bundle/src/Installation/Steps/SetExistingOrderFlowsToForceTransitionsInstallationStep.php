<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Installation\Steps;

use Doctrine\DBAL\Connection;

class SetExistingOrderFlowsToForceTransitionsInstallationStep
{
    public function __construct(private readonly Connection $db) {}

    public function install(): void
    {
        $this->db->executeStatement(
            <<<SQL
                UPDATE `flow_sequence`
                SET `config` = JSON_SET(`config`, '$.force_transition', TRUE)
                WHERE `action_name` = 'action.set.order.state'
                SQL
        );
    }
}
