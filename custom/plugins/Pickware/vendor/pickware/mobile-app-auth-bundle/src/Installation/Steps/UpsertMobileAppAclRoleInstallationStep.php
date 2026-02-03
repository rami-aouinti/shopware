<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\MobileAppAuthBundle\Installation\Steps;

use Doctrine\DBAL\Connection;
use Pickware\PhpStandardLibrary\Json\Json;

class UpsertMobileAppAclRoleInstallationStep
{
    // We need a static "magic" uuid to find this acl role. Using an easily readable 16 character string as the binary
    // representation of an uuid allows us to directly see its purpose as well.
    public const MOBILE_APP_ACL_ROLE_ID_BIN = '__pckwr_app_auth';
    private const MOBILE_APP_ACL_ROLE_NAME = 'Pickware Mobile App Authentication';
    private const MOBILE_APP_ACL_ROLE_PRIVILEGES = [
        'pickware_mobile_app.oauth',
        'language:read',
        'locale:read',
        'user:read',
    ];

    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function install(): void
    {
        $this->db->executeStatement(
            'INSERT INTO `acl_role` (
                `id`,
                `name`,
                `privileges`,
                `created_at`
            ) VALUES (
                :aclRoleId,
                :name,
                :privileges,
                UTC_TIMESTAMP(3)
            ) ON DUPLICATE KEY UPDATE
                `id` = `id`,
                `privileges` = :privileges,
                `updated_at` = UTC_TIMESTAMP(3)
            ',
            [
                'aclRoleId' => self::MOBILE_APP_ACL_ROLE_ID_BIN,
                'name' => self::MOBILE_APP_ACL_ROLE_NAME,
                'privileges' => Json::stringify(self::MOBILE_APP_ACL_ROLE_PRIVILEGES),
            ],
        );
    }
}
