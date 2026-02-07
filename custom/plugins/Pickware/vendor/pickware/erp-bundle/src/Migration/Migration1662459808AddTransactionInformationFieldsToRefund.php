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

class Migration1662459808AddTransactionInformationFieldsToRefund extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1662459808;
    }

    public function update(Connection $connection): void
    {
        // MySQL does not allow default values for JSON fields, therefore make the field transaction_information
        // nullable at first, then set the default value and finally remove the nullability
        $connection->executeStatement('
            ALTER TABLE `pickware_erp_return_order_refund`
                ADD `transaction_id` VARCHAR(255) NULL DEFAULT NULL AFTER `currency_iso_code`,
                ADD `transaction_information` JSON NULL CHECK (json_valid(`transaction_information`)) AFTER `transaction_id`;
        ');

        $connection->executeStatement('
            UPDATE `pickware_erp_return_order_refund`
            SET `transaction_information` = "{}"
            WHERE `transaction_information` IS NULL;
        ');

        $connection->executeStatement('
            ALTER TABLE `pickware_erp_return_order_refund`
                CHANGE `transaction_information` `transaction_information` JSON NOT NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
