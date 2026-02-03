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

class Migration1757085002AddTagsToReturnOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1757085002;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `pickware_erp_return_order_tag` (
                `return_order_id` BINARY(16) NOT NULL,
                `pickware_erp_return_order_version_id` BINARY(16) NOT NULL,
                `tag_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`return_order_id`, `pickware_erp_return_order_version_id`, `tag_id`),
                CONSTRAINT `pickware_erp_return_order_tag.fk.return_order`
                    FOREIGN KEY (`return_order_id`, `pickware_erp_return_order_version_id`) REFERENCES `pickware_erp_return_order` (`id`, `version_id`)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_erp_return_order_tag.fk.tag`
                    FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
