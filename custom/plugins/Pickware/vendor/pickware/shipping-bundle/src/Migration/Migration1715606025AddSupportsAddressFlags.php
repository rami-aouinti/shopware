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

class Migration1715606025AddSupportsAddressFlags extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1715606025;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_carrier`
                ADD COLUMN `supports_sender_address_for_shipments` TINYINT(1) NOT NULL DEFAULT 1 AFTER `batch_size`,
                ADD COLUMN `supports_receiver_address_for_return_shipments` TINYINT(1) NOT NULL DEFAULT 0 AFTER `supports_sender_address_for_shipments`;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
