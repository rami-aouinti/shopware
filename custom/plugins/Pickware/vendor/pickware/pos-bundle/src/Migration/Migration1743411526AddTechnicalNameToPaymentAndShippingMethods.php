<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1743411526AddTechnicalNameToPaymentAndShippingMethods extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1743411526;
    }

    public function update(Connection $connection): void
    {
        // Add the technical name to the payment methods that don't have one yet.
        $connection->executeStatement(
            'UPDATE `payment_method`
            SET `technical_name` = \'pw_pos_card\'
            WHERE `id` = UNHEX(\'7767147cc6d34632be77f494a3724d48\');',
        );
        $connection->executeStatement(
            'UPDATE `payment_method`
            SET `technical_name` = \'pw_pos_cash\'
            WHERE `id` = UNHEX(\'0f966e25cd1c4c4599331b0e91d63ce9\');',
        );
        $connection->executeStatement(
            'UPDATE `payment_method`
            SET `technical_name` = \'pw_pos_pay_on_collection\'
            WHERE `id` = UNHEX(\'c4a2c7003fd54a749cc89bbcfd8805f5\');',
        );

        // Add the technical name to the shipping methods that don't have one yet.
        $connection->executeStatement(
            'UPDATE `shipping_method`
            SET `technical_name` = \'pw_pos_pos_delivery\'
            WHERE `id` = UNHEX(\'923469263e0d4b58b38636346b8e5d6c\');',
        );
        $connection->executeStatement(
            'UPDATE `shipping_method`
            SET `technical_name` = \'pw_pos_click_and_collect\'
            WHERE `id` = UNHEX(\'b7805a59e9df43cc8a51c0a1704026d3\');',
        );
    }
}
