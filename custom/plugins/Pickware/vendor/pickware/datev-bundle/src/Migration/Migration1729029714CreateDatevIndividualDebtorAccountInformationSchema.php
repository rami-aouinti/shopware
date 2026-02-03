<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1729029714CreateDatevIndividualDebtorAccountInformationSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1729029714;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `pickware_datev_individual_debtor_account_information` (
                `id` BINARY(16) NOT NULL,
                `account` DECIMAL(8,0) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `import_export_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_datev_individual_debtor.uidx.account_per_export` (`account`, `import_export_id`),
                CONSTRAINT `pickware_datev_individual_debtor_account_information.fk.customer`
                    FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_datev_individual_debtor_account_information.fk.export`
                    FOREIGN KEY (`import_export_id`)
                    REFERENCES `pickware_erp_import_export` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            )
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
