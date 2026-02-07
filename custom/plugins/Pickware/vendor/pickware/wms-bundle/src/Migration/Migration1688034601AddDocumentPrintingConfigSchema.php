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

class Migration1688034601AddDocumentPrintingConfigSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1688034601;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_wms_document_printing_config` (
                `id` BINARY(16) NOT NULL,
                `shipping_method_id` BINARY(16) NOT NULL,
                `copies_of_invoices` INT(11) NOT NULL,
                `copies_of_delivery_notes` INT(11) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_wms_document_printing_config.uidx.shipping_method` (`shipping_method_id`),
                CONSTRAINT `pickware_wms_document_printing_config.fk.shipping_method`
                    FOREIGN KEY (`shipping_method_id`)
                    REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
