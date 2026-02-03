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

class Migration1726495219CreateGoodsReceiptDocumentMapping extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1726495219;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_erp_goods_receipt_document_mapping` (
                `goods_receipt_id` BINARY(16) NOT NULL,
                `pickware_document_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL DEFAULT (UTC_TIMESTAMP(3)),
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`goods_receipt_id`, `pickware_document_id`),
                UNIQUE INDEX `pickware_erp_goods_receipt_document_mapping.uidx.document` (`pickware_document_id`),
                CONSTRAINT `fk.pickware_erp_config.goods_receipt_id`
                    FOREIGN KEY (`goods_receipt_id`)
                    REFERENCES `pickware_erp_goods_receipt` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.pickware_erp_config.document_id`
                    FOREIGN KEY (`pickware_document_id`)
                    REFERENCES `pickware_document` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
