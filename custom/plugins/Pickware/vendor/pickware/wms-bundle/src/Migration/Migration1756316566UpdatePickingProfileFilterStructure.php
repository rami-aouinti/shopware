<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Migration;

use Doctrine\DBAL\Connection;
use Pickware\PhpStandardLibrary\Json\Json;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1756316566UpdatePickingProfileFilterStructure extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1756316566;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQl
                ALTER TABLE `pickware_wms_picking_profile`
                MODIFY COLUMN `filter` JSON NULL
                SQl,
        );

        $pickingProfiles = $connection->fetchAllAssociative(
            <<<SQL
                SELECT
                    `id`,
                    JSON_UNQUOTE(`filter`) AS `filter`
                FROM `pickware_wms_picking_profile`;
                SQL,
        );

        foreach ($pickingProfiles as $pickingProfile) {
            $rawOldFilter = Json::decodeToArray($pickingProfile['filter']);
            $newFilter = empty($rawOldFilter['_dalFilter']) ? null : $rawOldFilter['_dalFilter'];

            $connection->executeStatement(
                <<<SQL
                    UPDATE pickware_wms_picking_profile
                    SET `filter` = :filter
                    WHERE `id` = :id
                    SQL,
                [
                    'id' => $pickingProfile['id'],
                    'filter' => $newFilter ? Json::stringify($newFilter) : null,
                ],
            );
        }
    }

    public function updateDestructive(Connection $connection): void {}
}
