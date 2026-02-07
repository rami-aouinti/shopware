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

class Migration1768906602MakeBatchNumberOptional extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1768906602;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_erp_batch`
                DROP INDEX `pickware_erp_batch.uidx.number`,
                MODIFY COLUMN `number` VARCHAR(255) DEFAULT NULL,
                ADD COLUMN `unique_identifier` VARCHAR(255)
                    GENERATED ALWAYS AS (
                        COALESCE(
                            `number`,
                            CONCAT(
                                '__BBD__',
                                YEAR(`best_before_date`),
                                LPAD(MONTH(`best_before_date`), 2, '0'),
                                LPAD(DAYOFMONTH(`best_before_date`), 2, '0')
                            )
                        )
                    ) STORED AFTER `product_version_id`,
                -- Add a check constraint because MariaDB does not support NOT NULL for generated columns
                ADD CONSTRAINT `pickware_erp_batch.check.unique_identifier_not_null`
                    CHECK (`unique_identifier` IS NOT NULL),
                ADD UNIQUE INDEX `pickware_erp_batch.uidx.product_unique_identifier`
                    (`product_id`, `product_version_id`, `unique_identifier`);
                SQL,
        );
    }
}
