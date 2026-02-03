<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DocumentBundle\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1708602137AddTranslatedDescriptionField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1708602137;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_document_type`
            ADD COLUMN `singular_description` JSON NOT NULL AFTER `description`,
            ADD COLUMN `plural_description` JSON NOT NULL AFTER `singular_description`;',
        );

        $connection->executeStatement(
            'UPDATE `pickware_document_type`
            SET
                singular_description = JSON_OBJECT(
                    "de",
                    `description`,
                    "en",
                    `description`
                ),
                plural_description = JSON_OBJECT(
                    "de",
                    `description`,
                    "en",
                    `description`
                );',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_document_type`
            DROP COLUMN `description`;',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_document_type`
            ADD COLUMN `description` VARCHAR(255) GENERATED ALWAYS AS (json_unquote(json_extract(`singular_description`,\'$."de"\'))) STORED AFTER `plural_description`;',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
