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

class Migration1716561754UseLocalesInTranslatedDescriptionFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1716561754;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                UPDATE `pickware_document_type`
                SET
                    `singular_description` = JSON_INSERT(
                        `singular_description`,
                        '$."de-DE"', JSON_EXTRACT(`singular_description`, '$."de"'),
                        '$."en-GB"', JSON_EXTRACT(`singular_description`, '$."en"')
                    ),
                    `plural_description` = JSON_INSERT(
                        `plural_description`,
                        '$."de-DE"', JSON_EXTRACT(`plural_description`, '$."de"'),
                        '$."en-GB"', JSON_EXTRACT(`plural_description`, '$."en"')
                    );
                SQL,
        );

        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_document_type`
                DROP COLUMN `description`;
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
