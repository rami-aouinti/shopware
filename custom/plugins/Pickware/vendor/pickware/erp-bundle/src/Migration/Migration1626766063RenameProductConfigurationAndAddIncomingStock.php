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
use Pickware\InstallationLibrary\MailTemplate\MailTemplateUpdater;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1626766063RenameProductConfigurationAndAddIncomingStock extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1626766063;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_product_configuration` RENAME `pickware_erp_pickware_product`;',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_erp_pickware_product`
            ADD COLUMN `incoming_stock` INT(11) NOT NULL DEFAULT 0 AFTER `reorder_point`',
        );

        // Delete all current demand planning sessions and list items, so the user has to recalculate the demand with
        // the new incoming stock property
        $connection->executeStatement('DELETE FROM `pickware_erp_demand_planning_session`;');
        $connection->executeStatement('DELETE FROM `pickware_erp_demand_planning_list_item`;');

        // Update mail template which reference the pickwareErpProductConfiguration extension
        $mailTemplateUpdater = new MailTemplateUpdater($connection);
        $mailTemplateUpdater->replaceStringInContentsOfAllMailTemplates(
            'pickwareErpProductConfiguration',
            'pickwareErpPickwareProduct',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
