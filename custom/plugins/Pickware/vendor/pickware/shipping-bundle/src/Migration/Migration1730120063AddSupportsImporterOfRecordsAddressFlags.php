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

class Migration1730120063AddSupportsImporterOfRecordsAddressFlags extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1730120063;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_carrier`
                ADD COLUMN `supports_importer_of_records_address` TINYINT(1) NOT NULL DEFAULT 0 AFTER `supports_receiver_address_for_return_shipments`;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
