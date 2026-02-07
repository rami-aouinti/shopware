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
use Pickware\DalBundle\Sql\SqlUuid;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1736336746ActivateShowProductVariantsFilterInUserConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1736336746;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `user_config`
            SET `value` = IF(
                JSON_TYPE(`value`) = "ARRAY",
                JSON_OBJECT(
                    "pwErpShowVariantsFilter",
                    JSON_OBJECT(
                        "value", TRUE,
                        "criteria", JSON_ARRAY(
                        JSON_OBJECT(
                            "type", "equals",
                            "field", "pwErpShowVariantsFilter",
                            "value", TRUE
                            )
                        )
                    )
                ),
                JSON_SET(
                    `value`,
                    "$.pwErpShowVariantsFilter",
                    JSON_OBJECT(
                        "value", TRUE,
                        "criteria", JSON_ARRAY(
                            JSON_OBJECT(
                                "type", "equals",
                                "field", "pwErpShowVariantsFilter",
                                "value", TRUE
                            )
                        )
                    )
                )
            )
            WHERE `key` = "grid.filter.product"',
        );

        // For user_configs that do not have the key "grid.filter.product" yet, we need to insert a new user_config
        $connection->executeStatement(
            'INSERT INTO `user_config` (`id`, `user_id`, `key`, `value`, `created_at`)
            SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                `user`.`id`,
                "grid.filter.product",
                JSON_OBJECT(
                    "pwErpShowVariantsFilter",
                    JSON_OBJECT(
                        "value", TRUE,
                        "criteria", JSON_ARRAY(
                        JSON_OBJECT(
                            "type", "equals",
                            "field", "pwErpShowVariantsFilter",
                            "value", TRUE
                            )
                        )
                    )
                ),
                UTC_TIMESTAMP(3)
            FROM `user`
            LEFT JOIN `user_config` ON `user`.`id` = `user_config`.`user_id` AND `user_config`.`key` = "grid.filter.product"
            WHERE `user_config`.`id` IS NULL',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
