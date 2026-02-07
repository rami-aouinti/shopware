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
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1708000885ChangeDeliveryToOrderFKToOnDeleteRestrict extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1708000885;
    }

    // phpcs:disable ShopwarePlugins.Migration.ForeignKeyIndexPair.MissingDropIndex
    // Is already re-created in this migration and we do not touch it retrospectively.
    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_delivery`
                DROP FOREIGN KEY `pickware_wms_delivery.fk.order`;
            ALTER TABLE `pickware_wms_delivery`
                ADD CONSTRAINT `pickware_wms_delivery.fk.order`
                FOREIGN KEY (`order_id`, `order_version_id`)
                REFERENCES `order` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE CASCADE;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
