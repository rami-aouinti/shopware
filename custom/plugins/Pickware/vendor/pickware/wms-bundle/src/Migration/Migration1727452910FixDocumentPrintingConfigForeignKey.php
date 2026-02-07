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

class Migration1727452910FixDocumentPrintingConfigForeignKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1727452910;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                DELETE `pickware_wms_document_printing_config`
                FROM `pickware_wms_document_printing_config`
                LEFT JOIN `shipping_method`
                    ON `pickware_wms_document_printing_config`.`shipping_method_id` = `shipping_method`.`id`
                WHERE `shipping_method`.`id` IS NULL
                SQL,
        );

        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_wms_document_printing_config`
                ADD CONSTRAINT `pickware_wms_document_printing_config.fk.shipping_method`
                    FOREIGN KEY (`shipping_method_id`)
                    REFERENCES `shipping_method` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
