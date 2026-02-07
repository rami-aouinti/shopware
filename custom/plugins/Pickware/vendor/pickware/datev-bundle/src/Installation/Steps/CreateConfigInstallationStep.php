<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DatevBundle\Installation\Steps;

use Doctrine\DBAL\Connection;
use Pickware\DatevBundle\Config\ConfigService;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class CreateConfigInstallationStep
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function install(): void
    {
        $this->db->executeStatement(
            'INSERT INTO pickware_datev_config (
                `id`,
                `sales_channel_id`,
                `values`,
                `created_at`
            ) VALUES (
                UNHEX(:id),
                NULL,
                "{}",
                UTC_TIMESTAMP(3)
            ) ON DUPLICATE KEY UPDATE `id` = `id`',
            ['id' => ConfigService::DEFAULT_CONFIG_ID],
        );
    }
}
