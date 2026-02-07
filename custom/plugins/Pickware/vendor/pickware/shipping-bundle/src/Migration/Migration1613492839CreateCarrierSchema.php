<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Migration;

use Doctrine\DBAL\Connection;
use function Pickware\InstallationLibrary\Migration\ensureCorrectCollationOfColumnForForeignKeyConstraint;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1613492839CreateCarrierSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1613492839;
    }

    public function update(Connection $db): void
    {
        ensureCorrectCollationOfColumnForForeignKeyConstraint(
            $db,
            'mail_template_type',
            'technical_name',
        );
        $db->executeStatement(
            'CREATE TABLE IF NOT EXISTS `pickware_shipping_carrier` (
                `technical_name` VARCHAR(255) NOT NULL,
                `name` varchar(255) NOT NULL,
                `abbreviation` VARCHAR(255) NOT NULL,
                `config_domain` VARCHAR(255) NOT NULL,
                `shipment_config_default_values` JSON NOT NULL,
                `shipment_config_options` JSON NOT NULL,
                `default_parcel_packing_configuration` JSON NOT NULL,
                `return_label_mail_template_type_technical_name` VARCHAR(255) DEFAULT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) DEFAULT NULL,
                PRIMARY KEY (`technical_name`),
                CONSTRAINT `pickware_shipping_carrier.fk.return_label_mail_template`
                    FOREIGN KEY (`return_label_mail_template_type_technical_name`)
                    REFERENCES `mail_template_type` (`technical_name`)
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
