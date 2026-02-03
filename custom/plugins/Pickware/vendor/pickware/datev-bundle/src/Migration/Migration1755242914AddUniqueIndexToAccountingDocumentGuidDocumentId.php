<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1755242914AddUniqueIndexToAccountingDocumentGuidDocumentId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1755242914;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
                DELETE
                    guid1
                FROM
                    `pickware_datev_accounting_document_guid` guid1
                JOIN
                    `pickware_datev_accounting_document_guid` guid2
                    ON guid1.document_id = guid2.document_id
                    AND (
                        guid1.created_at > guid2.created_at
                        OR (guid1.created_at = guid2.created_at AND guid1.id > guid2.id)
                    );
            SQL);

        $connection->executeStatement(<<<SQL
                ALTER TABLE `pickware_datev_accounting_document_guid`
                ADD UNIQUE INDEX `pickware_datev_accounting_document_guid.uniq.document_id` (`document_id`)
            SQL);
    }

    public function updateDestructive(Connection $connection): void {}
}
