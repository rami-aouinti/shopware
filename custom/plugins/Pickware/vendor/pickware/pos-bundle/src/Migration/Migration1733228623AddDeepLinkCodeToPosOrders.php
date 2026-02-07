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

class Migration1733228623AddDeepLinkCodeToPosOrders extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1733228623;
    }

    public function update(Connection $connection): void
    {
        // The inner join in the query is necessary to ensure that the deep link code is only generated for each order
        // once, ignoring the order version.
        // The deep link code generation is a port of the Shopware\Core\Framework\Util\Random::getBase64UrlString function.
        // This includes making the deep link code safe to be used in URLs.
        $connection->executeStatement('
            UPDATE `order`
            INNER JOIN (
                SELECT `id`,
                       REPLACE(REPLACE(REPLACE(LEFT(to_base64(RANDOM_BYTES(32)), 32), "+", "-"), "/", "_"), "=", "_") as `new_deep_link_code`
                FROM `order`
                WHERE `deep_link_code` IS NULL
                  AND JSON_EXTRACT(`custom_fields`, "$.isPosOrder") = true
                GROUP BY `id`
            ) as `new_deep_link_codes`
            ON `order`.`id` = `new_deep_link_codes`.`id`
            SET `deep_link_code` = `new_deep_link_codes`.`new_deep_link_code`
            WHERE `order`.`id` = `new_deep_link_codes`.`id`;
        ');
    }
}
