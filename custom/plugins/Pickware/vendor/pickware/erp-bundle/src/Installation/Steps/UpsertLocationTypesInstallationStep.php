<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Installation\Steps;

use Doctrine\DBAL\Connection;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;

class UpsertLocationTypesInstallationStep
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function install(): void
    {
        foreach (LocationTypeDefinition::TECHNICAL_NAMES as $technicalName) {
            $this->db->executeStatement(
                'INSERT INTO pickware_erp_location_type (
                    technical_name,
                    internal,
                    created_at
                ) VALUES (
                    :technicalName,
                    :internal,
                    UTC_TIMESTAMP(3)
                ) ON DUPLICATE KEY UPDATE
                    technical_name = :technicalName,
                    internal = :internal',
                [
                    'technicalName' => $technicalName,
                    'internal' => (int) in_array(
                        $technicalName,
                        LocationTypeDefinition::TECHNICAL_NAMES_INTERNAL,
                        true,
                    ),
                ],
            );
        }
    }
}
