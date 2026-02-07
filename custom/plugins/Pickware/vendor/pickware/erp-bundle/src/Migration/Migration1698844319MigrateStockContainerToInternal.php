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
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1698844319MigrateStockContainerToInternal extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1698844319;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `product`
            INNER JOIN (
                SELECT
                    `stock`.`product_id` as `product_id`,
                    `stock`.`product_version_id` as `product_version_id`,
                    SUM(`stock`.`quantity`) AS `quantity`
                FROM `pickware_erp_stock` `stock`
                WHERE `stock`.`location_type_technical_name` = :stockContainerLocationTypeTechnicalName
                AND `stock`.`product_version_id` = :liveVersionId
                GROUP BY
                    `stock`.`product_id`,
                    `stock`.`product_version_id`
            ) AS `stockContainerStock`
                ON `stockContainerStock`.`product_id` = `product`.`id`
                   AND `stockContainerStock`.`product_version_id` = `product`.`version_id`
            SET `product`.`stock` = `product`.`stock` + `stockContainerStock`.`quantity`,
                `product`.`available_stock` = `product`.`available_stock` + `stockContainerStock`.`quantity`
            WHERE `product`.`version_id` = :liveVersionId',
            [
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
                'stockContainerLocationTypeTechnicalName' => LocationTypeDefinition::TECHNICAL_NAME_STOCK_CONTAINER,
            ],
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
