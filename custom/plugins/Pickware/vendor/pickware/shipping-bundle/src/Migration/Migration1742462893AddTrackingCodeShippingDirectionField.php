<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1742462893AddTrackingCodeShippingDirectionField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1742462893;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_tracking_code`
                ADD COLUMN `shipping_direction` ENUM("outgoing", "incoming") NOT NULL DEFAULT "outgoing" AFTER `meta_information`;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
