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
use Pickware\DalBundle\Sql\SqlUuid;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1758096732MigrateSpecialCustomFieldsOnDocumentsToNewDocumentTypeCustomFieldMapping extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1758096732;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            INSERT INTO `pickware_erp_document_type_custom_field_mapping` (
                `id`,
                `document_type_id`,
                `custom_field_id`,
                `position`,
                `entity_type`,
                `created_at`
            ) SELECT
                ' . SqlUuid::UUID_V4_GENERATION . ',
                dt.id AS `document_type_id`,
                cf.id AS `custom_field_id`,
                1 AS `position`,
                mapping.entity_type,
                UTC_TIMESTAMP(3) AS `created_at`
            FROM (
                SELECT "pickware_erp_picklist" as document_type_name, "pickware_erp_starter_picking_instruction" as custom_field_name, "order" as entity_type
                UNION ALL
                SELECT "pickware_erp_picklist", "pickware_erp_starter_picking_instruction", "product"
                UNION ALL
                SELECT "invoice", "pickware_erp_starter_invoice_comment", "order"
            ) AS mapping
            INNER JOIN `document_type` dt ON dt.technical_name = mapping.document_type_name
            INNER JOIN `custom_field` cf ON cf.name = mapping.custom_field_name
        ');
    }

    public function updateDestructive(Connection $connection): void {}
}
