<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary;

use Doctrine\DBAL\Connection;

class IdLookUpService
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function lookUpLanguageIdForLocaleCode(string $localeCode): ?string
    {
        return $this->db->fetchOne(
            'SELECT `language`.`id`
            FROM `language`
            INNER JOIN `locale`
                ON `language`.`locale_id` = `locale`.`id`
            WHERE
                `locale`.`code` = :localeCode',
            [
                'localeCode' => $localeCode,
            ],
        ) ?: null;
    }
}
