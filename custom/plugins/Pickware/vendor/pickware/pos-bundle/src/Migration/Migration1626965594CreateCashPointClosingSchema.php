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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionDefinition;
use Pickware\PickwarePos\CashPointClosing\Model\CashPointClosingTransactionLineItemDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1626965594CreateCashPointClosingSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1626965594;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_pos_cash_point_closing` (
                `id` BINARY(16) NOT NULL,
                `cash_register_fiskaly_client_uuid` VARCHAR(255) NULL,
                `number` INTEGER NOT NULL,
                `export_creation_date` DATETIME(3) NOT NULL,
                `cash_statement` JSON NOT NULL,
                `user_id` BINARY(16) NULL,
                `user_snapshot` JSON NOT NULL,
                `cash_register_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_pos_cash_point_closing.uidx.cash_register_number` (`cash_register_id`, `number`),
                CONSTRAINT `pickware_pos_cash_point_closing.fk.cash_register`
                    FOREIGN KEY (`cash_register_id`)
                    REFERENCES `pickware_pos_cash_register` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_pos_cash_point_closing.fk.user`
                    FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_pos_cash_point_closing_transaction` (
                `id` BINARY(16) NOT NULL,
                `cash_register_id` BINARY(16) NOT NULL,
                `cash_register_fiskaly_client_uuid` VARCHAR(255) NULL,
                `cash_point_closing_id` BINARY(16) NULL,
                `currency_id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NULL,
                `number` INT(11) NOT NULL,
                `type` ENUM(:transactionTypes) NOT NULL,
                `name` VARCHAR(60) NULL,
                `start` DATETIME(3) NOT NULL,
                `end` DATETIME(3) NOT NULL,
                `user_id` BINARY(16) NULL,
                `user_snapshot` JSON NOT NULL,
                `buyer` JSON NOT NULL,
                `total` JSON NOT NULL,
                `payment` JSON NOT NULL,
                `comment` VARCHAR(255) NULL,
                `fiskaly_tss_transaction_uuid` VARCHAR(255) NULL,
                `fiskaly_tss_error_message` TEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `pickware_pos_cash_transaction.uidx.cash_register_number` (`cash_register_id`, `number`),
                CONSTRAINT `pickware_pos_cash_transaction.fk.cash_register`
                    FOREIGN KEY (`cash_register_id`)
                    REFERENCES `pickware_pos_cash_register` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_pos_cash_transaction.fk.cash_point_closing`
                    FOREIGN KEY (`cash_point_closing_id`)
                    REFERENCES `pickware_pos_cash_point_closing` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_pos_cash_transaction.fk.currency`
                    FOREIGN KEY (`currency_id`)
                    REFERENCES `currency` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `pickware_pos_cash_transaction.fk.user`
                    FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `pickware_pos_cash_transaction.fk.customer`
                    FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
            ['transactionTypes' => CashPointClosingTransactionDefinition::TYPES],
            ['transactionTypes' => ArrayParameterType::STRING],
        );
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_pos_cash_point_closing_transaction_line_item` (
                `id` BINARY(16) NOT NULL,
                `cash_point_closing_transaction_id` BINARY(16) NOT NULL,
                `referenced_cash_point_closing_transaction_id` BINARY(16) NULL,
                `product_id` BINARY(16) NULL,
                `product_version_id` BINARY(16) NULL,
                `name` VARCHAR(255) NOT NULL,
                `product_number` VARCHAR(255) NOT NULL,
                `gtin` VARCHAR(255) NULL,
                `voucher_id` VARCHAR(255) NULL,
                `type` ENUM(:transactionLineItemTypes) NOT NULL,
                `quantity` INT(11) NOT NULL,
                `tax_id` BINARY(16) NULL,
                `tax_rate` DECIMAL(10,2) NOT NULL,
                `price_per_unit` JSON NOT NULL,
                `total` JSON NOT NULL,
                `discount` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `pickware_pos_cash_transaction_line_item.fk.transaction`
                    FOREIGN KEY (`cash_point_closing_transaction_id`)
                    REFERENCES `pickware_pos_cash_point_closing_transaction` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `pickware_pos_cash_transaction_line_item.fk.ref_transaction`
                    FOREIGN KEY (`referenced_cash_point_closing_transaction_id`)
                    REFERENCES `pickware_pos_cash_point_closing_transaction` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `pickware_pos_cash_transaction_line_item.fk.product`
                    FOREIGN KEY (`product_id`,`product_version_id`)
                    REFERENCES `product` (`id`,`version_id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `pickware_pos_cash_transaction_line_item.fk.tax`
                    FOREIGN KEY (`tax_id`)
                    REFERENCES `tax` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
            ['transactionLineItemTypes' => CashPointClosingTransactionLineItemDefinition::TYPES],
            ['transactionLineItemTypes' => ArrayParameterType::STRING],
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
