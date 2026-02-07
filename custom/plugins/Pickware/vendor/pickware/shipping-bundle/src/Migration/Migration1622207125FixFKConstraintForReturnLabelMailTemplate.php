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

class Migration1622207125FixFKConstraintForReturnLabelMailTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1622207125;
    }

    // phpcs:disable ShopwarePlugins.Migration.ForeignKeyIndexPair.MissingDropIndex
    // Is already re-created in this migration and we do not touch it retrospectively.
    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_carrier`
            DROP FOREIGN KEY `pickware_shipping_carrier.fk.return_label_mail_template`;',
        );

        ensureCorrectCollationOfColumnForForeignKeyConstraint(
            $connection,
            'pickware_shipping_carrier',
            'return_label_mail_template_type_technical_name',
        );
        ensureCorrectCollationOfColumnForForeignKeyConstraint(
            $connection,
            'mail_template_type',
            'technical_name',
        );
        // This migration updates the foreign key constraint:
        // ON DELETE CASCADE -> SET NULL
        // ON UPDATE RESTRICT -> CASCADE
        $connection->executeStatement(
            'ALTER TABLE `pickware_shipping_carrier`
            ADD CONSTRAINT `pickware_shipping_carrier.fk.return_label_mail_template`
                FOREIGN KEY (`return_label_mail_template_type_technical_name`)
                REFERENCES `mail_template_type`(`technical_name`)
                ON DELETE SET NULL
                ON UPDATE CASCADE;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
