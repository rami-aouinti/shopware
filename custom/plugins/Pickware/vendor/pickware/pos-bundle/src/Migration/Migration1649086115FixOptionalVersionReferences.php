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
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1649086115FixOptionalVersionReferences extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1649086115;
    }

    public function update(Connection $connection): void
    {
        $connection->transactional(
            function(Connection $connection): void {
                // Then null all broken references (because the reference was deleted)
                $connection->executeStatement(
                    <<<SQL
                        UPDATE `pickware_pos_cash_point_closing_transaction_line_item` `line_item`
                        LEFT JOIN `product`
                            ON line_item.`product_id` = `product`.`id` AND `product`.`version_id` = :liveVersionId
                        SET `line_item`.`product_id` = NULL
                        WHERE `product`.`id` IS NULL
                        SQL,
                    ['liveVersionId' => hex2bin(Defaults::LIVE_VERSION)],
                );

                $connection->executeStatement(
                    <<<SQL
                        UPDATE `pickware_pos_cash_point_closing_transaction_line_item`
                        SET `product_version_id` = :liveVersionId
                        WHERE `product_id` IS NOT NULL
                        SQL,
                    ['liveVersionId' => hex2bin(Defaults::LIVE_VERSION)],
                );
            },
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
